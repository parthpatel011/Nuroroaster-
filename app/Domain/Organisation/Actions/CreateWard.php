<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Actions;

use App\Domain\Organisation\Models\Ward;

final class CreateWard
{
    public function __invoke(string $code, string $name): Ward
    {
        return Ward::query()->create([
            'code' => $code,
            'name' => $name,
            'status' => 'active',
        ]);
    }
}
