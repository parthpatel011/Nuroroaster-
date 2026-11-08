<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Models;

use App\Domain\Shifts\Models\ShiftDefinition;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutyAssignment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'roster_id',
        'employee_id',
        'shift_definition_id',
        'duty_date',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duty_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftDefinition(): BelongsTo
    {
        return $this->belongsTo(ShiftDefinition::class);
    }
}
