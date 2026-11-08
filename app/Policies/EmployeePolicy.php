<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Staff\Models\Employee;
use App\Policies\Concerns\ChecksTenantRole;

class EmployeePolicy
{
    use ChecksTenantRole;

    public function viewAny(User $user): bool
    {
        return $this->role() !== null;
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->role() !== null;
    }

    public function create(User $user): bool
    {
        return $this->role()?->managesHospital() ?? false;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->role()?->managesHospital() ?? false;
    }
}
