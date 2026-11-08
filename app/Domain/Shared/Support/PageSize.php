<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use Illuminate\Http\Request;

final class PageSize
{
    public const DEFAULT = 25;

    public const MAX = 50;

    public static function from(Request $request): int
    {
        $value = $request->integer('per_page', self::DEFAULT);

        if ($value < 1) {
            $value = self::DEFAULT;
        }

        return min($value, self::MAX);
    }
}
