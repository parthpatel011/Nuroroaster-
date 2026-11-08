<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Actions;

use App\Domain\Rosters\Exceptions\RosterDomainException;
use App\Domain\Rosters\Models\DutyAssignment;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Shared\Support\ErrorCode;
use Illuminate\Support\Facades\DB;

final class RemoveAssignment
{
    public function __invoke(Roster $roster, DutyAssignment $assignment, int $lockVersion): void
    {
        if ($assignment->roster_id !== $roster->id || $assignment->status !== 'scheduled') {
            throw new RosterDomainException(ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND, 'The assignment was not found.', 404);
        }

        DB::transaction(function () use ($roster, $assignment, $lockVersion): void {
            $updated = Roster::query()
                ->whereKey($roster->id)
                ->where('lock_version', $lockVersion)
                ->update([
                    'lock_version' => $lockVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                throw new RosterDomainException(
                    ErrorCode::STALE_VERSION,
                    'The roster changed. Reload it and try again.',
                    409,
                );
            }

            $assignment->status = 'cancelled';
            $assignment->save();
        });
    }
}
