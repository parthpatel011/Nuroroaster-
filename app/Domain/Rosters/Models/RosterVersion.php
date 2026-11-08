<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable publish snapshot row. No application service writes this yet.
 */
class RosterVersion extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'roster_id',
        'version_no',
        'snapshot',
        'snapshot_sha256',
        'published_by',
        'published_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
            'version_no' => 'integer',
        ];
    }
}
