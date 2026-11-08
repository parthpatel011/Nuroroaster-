<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Staff\Models\Employee;

final class UpdateEmployee
{
    public function __invoke(Employee $employee, string $status, ?string $inactiveFrom): Employee
    {
        $employee->fill([
            'status' => $status,
            'inactive_from' => $inactiveFrom,
        ])->save();

        return $employee->refresh();
    }
}
