<?php

declare(strict_types=1);

namespace App\Domain\Staff\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'employee_code',
        'full_name',
        'status',
        'inactive_from',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'inactive_from' => 'date',
        ];
    }

    public function isBlockedOn(string $dutyDate): bool
    {
        if ($this->status === 'inactive') {
            return true;
        }

        return $this->inactive_from !== null
            && $dutyDate >= $this->inactive_from->toDateString();
    }
}
