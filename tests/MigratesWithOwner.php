<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MigratesWithOwner
{
    use RefreshDatabase {
        migrateFreshUsing as frameworkMigrateFreshUsing;
    }

    /**
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing(): array
    {
        return array_merge($this->frameworkMigrateFreshUsing(), [
            '--database' => 'pgsql_owner',
            '--force' => true,
        ]);
    }
}
