<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Organisation\Models\Ward;
use App\Domain\Rosters\Exceptions\RosterDomainException;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Shared\Support\PostgresExceptionMapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CreateRoster
{
    public function __invoke(User $author, Ward $ward, string $periodStart, string $periodEnd): Roster
    {
        if ($ward->status !== 'active') {
            throw new RosterDomainException('WARD_INACTIVE', 'Inactive wards cannot receive a new roster.', 422);
        }

        try {
            return DB::transaction(function () use ($author, $ward, $periodStart, $periodEnd): Roster {
                return Roster::query()->create([
                    'ward_id' => $ward->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'status' => 'draft',
                    'lock_version' => 0,
                    'created_by' => $author->id,
                ]);
            });
        } catch (QueryException $exception) {
            $mapped = PostgresExceptionMapper::map($exception);

            if ($mapped !== null) {
                throw $mapped;
            }

            throw $exception;
        }
    }
}
