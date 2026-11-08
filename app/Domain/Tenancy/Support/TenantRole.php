<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Support;

enum TenantRole: string
{
    case HospitalAdmin = 'hospital_admin';
    case Supervisor = 'supervisor';
    case Staff = 'staff';

    public function managesRoster(): bool
    {
        return $this === self::HospitalAdmin || $this === self::Supervisor;
    }

    public function managesHospital(): bool
    {
        return $this === self::HospitalAdmin;
    }
}
