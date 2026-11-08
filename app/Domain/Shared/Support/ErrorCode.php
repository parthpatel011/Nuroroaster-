<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

final class ErrorCode
{
    public const ASSIGNMENT_OVERLAP = 'ASSIGNMENT_OVERLAP';

    public const STAFF_INACTIVE = 'STAFF_INACTIVE';

    public const SHIFT_INACTIVE = 'SHIFT_INACTIVE';

    public const STALE_VERSION = 'STALE_VERSION';

    public const ROSTER_NOT_DRAFT = 'ROSTER_NOT_DRAFT';

    public const DUTY_OUTSIDE_PERIOD = 'DUTY_OUTSIDE_PERIOD';

    public const DUTY_DATE_MISMATCH = 'DUTY_DATE_MISMATCH';

    public const ASSIGNMENT_TARGET_NOT_FOUND = 'ASSIGNMENT_TARGET_NOT_FOUND';

    public const ROSTER_PERIOD_OVERLAP = 'ROSTER_PERIOD_OVERLAP';

    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const FORBIDDEN = 'FORBIDDEN';
}
