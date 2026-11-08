<?php

declare(strict_types=1);

namespace App\Domain\Shifts\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShiftDefinition extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'kind',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
        ];
    }

    public function startTime(): string
    {
        return substr((string) $this->start_time, 0, 8);
    }

    public function endTime(): string
    {
        return substr((string) $this->end_time, 0, 8);
    }
}
