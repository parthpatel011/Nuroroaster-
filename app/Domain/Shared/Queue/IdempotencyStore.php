<?php

declare(strict_types=1);

namespace App\Domain\Shared\Queue;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdempotencyStore
{
    public function claim(string $tenantId, string $scope, string $key): bool
    {
        try {
            DB::transaction(function () use ($tenantId, $scope, $key): void {
                DB::table('idempotency_keys')->insert([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenantId,
                    'scope' => $scope,
                    'idempotency_key' => $key,
                    'created_at' => now(),
                ]);
            });

            return true;
        } catch (QueryException $exception) {
            if ($this->sqlState($exception) === '23505') {
                return false;
            }

            throw $exception;
        }
    }

    private function sqlState(QueryException $exception): string
    {
        $code = $exception->errorInfo[0] ?? $exception->getCode();

        return is_string($code) ? $code : '';
    }
}
