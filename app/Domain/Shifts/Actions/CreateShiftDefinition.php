<?php

declare(strict_types=1);

namespace App\Domain\Shifts\Actions;

use App\Domain\Shifts\Models\ShiftDefinition;

final class CreateShiftDefinition
{
    public function __invoke(
        string $code,
        string $name,
        string $startTime,
        string $endTime,
        int $breakMinutes,
    ): ShiftDefinition {
        return ShiftDefinition::query()->create([
            'code' => $code,
            'name' => $name,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'break_minutes' => $breakMinutes,
            'kind' => 'duty',
            'status' => 'active',
        ]);
    }
}
