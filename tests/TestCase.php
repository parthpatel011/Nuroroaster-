<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function asMember(User $user, string $membershipId): static
    {
        return $this->actingAs($user)
            ->withSession([
                '_token' => 'test-csrf',
                'membership_id' => $membershipId,
            ])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf')
            ->withHeader('Accept', 'application/json');
    }

    protected function expectQueryFailure(callable $callback): QueryException
    {
        DB::beginTransaction();

        try {
            $callback();
        } catch (QueryException $exception) {
            DB::rollBack();

            return $exception;
        }

        DB::rollBack();
        $this->fail('Expected a database exception.');
    }
}
