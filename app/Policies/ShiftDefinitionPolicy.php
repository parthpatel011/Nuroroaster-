<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Policies\Concerns\ChecksTenantRole;

class ShiftDefinitionPolicy
{
    use ChecksTenantRole;

    public function viewAny(User $user): bool
    {
        return $this->role() !== null;
    }

    public function create(User $user): bool
    {
        return $this->role()?->managesHospital() ?? false;
    }

    public function view(User $user, ShiftDefinition $shiftDefinition): bool
    {
        return $this->role() !== null;
    }
}
