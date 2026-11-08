<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organisation\Models\Ward;
use App\Policies\Concerns\ChecksTenantRole;

class WardPolicy
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

    public function view(User $user, Ward $ward): bool
    {
        return $this->role() !== null;
    }
}
