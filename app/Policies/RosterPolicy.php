<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Rosters\Models\Roster;
use App\Policies\Concerns\ChecksTenantRole;

class RosterPolicy
{
    use ChecksTenantRole;

    public function viewAny(User $user): bool
    {
        return $this->role() !== null;
    }

    public function view(User $user, Roster $roster): bool
    {
        return $this->role() !== null;
    }

    public function create(User $user): bool
    {
        return $this->role()?->managesRoster() ?? false;
    }

    public function assign(User $user, Roster $roster): bool
    {
        return $this->role()?->managesRoster() ?? false;
    }
}
