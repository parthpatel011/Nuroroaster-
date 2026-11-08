<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Models;

use App\Domain\Organisation\Models\Ward;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roster extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'ward_id',
        'period_start',
        'period_end',
        'status',
        'lock_version',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'lock_version' => 'integer',
        ];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DutyAssignment::class);
    }
}
