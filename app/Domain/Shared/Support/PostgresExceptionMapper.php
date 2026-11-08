<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Rosters\Exceptions\RosterDomainException;
use Illuminate\Database\QueryException;

final class PostgresExceptionMapper
{
    public static function map(QueryException $exception): ?RosterDomainException
    {
        $message = $exception->getMessage();
        $state = $exception->errorInfo[0] ?? '';
        $state = is_string($state) ? $state : '';

        if ($state === '23P01' && str_contains($message, 'rosters_no_period_overlap')) {
            return new RosterDomainException(
                ErrorCode::ROSTER_PERIOD_OVERLAP,
                'This ward already has a roster covering those dates.',
                409,
            );
        }

        if ($state === '23P01' || str_contains($message, 'duty_assignments_no_overlap')) {
            return new RosterDomainException(
                ErrorCode::ASSIGNMENT_OVERLAP,
                'This staff member already has a duty that overlaps this shift.',
                409,
            );
        }

        $codes = [
            ErrorCode::STAFF_INACTIVE => [422, 'Inactive staff cannot be assigned.'],
            ErrorCode::SHIFT_INACTIVE => [422, 'Inactive shift definitions cannot be assigned.'],
            ErrorCode::ROSTER_NOT_DRAFT => [409, 'Only a draft roster can be edited.'],
            ErrorCode::DUTY_OUTSIDE_PERIOD => [422, 'The duty date is outside the roster period.'],
            ErrorCode::DUTY_DATE_MISMATCH => [422, 'The duty date does not match the hospital-local start of the shift.'],
            ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND => [404, 'The staff member, shift, or roster was not found.'],
        ];

        foreach ($codes as $code => [$status, $detail]) {
            if (str_contains($message, $code)) {
                return new RosterDomainException($code, $detail, $status);
            }
        }

        if ($state === '23503') {
            return new RosterDomainException(
                ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND,
                'The staff member, shift, or roster was not found.',
                404,
            );
        }

        if ($state === '23514' && str_contains($message, 'duty_assignments_duration_check')) {
            return new RosterDomainException(
                ErrorCode::DUTY_DATE_MISMATCH,
                'A duty must be longer than zero and no more than 24 hours.',
                422,
            );
        }

        return null;
    }
}
