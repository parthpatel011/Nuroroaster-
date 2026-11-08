<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'timezone',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Tenant $tenant): void {
            if (! in_array($tenant->timezone, timezone_identifiers_list(), true)) {
                throw new InvalidArgumentException('Hospital timezone must be an IANA timezone.');
            }
        });
    }
}
