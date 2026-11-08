<?php

declare(strict_types=1);

namespace App\Domain\Staff\Actions;

use App\Domain\Staff\Models\Employee;

final class CreateEmployee
{
    public function __invoke(string $employeeCode, string $fullName): Employee
    {
        return Employee::query()->create([
            'employee_code' => $employeeCode,
            'full_name' => $fullName,
            'status' => 'active',
        ]);
    }
}
