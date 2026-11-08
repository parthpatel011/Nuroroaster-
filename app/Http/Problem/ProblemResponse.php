<?php

declare(strict_types=1);

namespace App\Http\Problem;

use Illuminate\Http\JsonResponse;

final class ProblemResponse
{
    public static function make(int $status, string $code, string $title, string $detail): JsonResponse
    {
        return new JsonResponse([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
        ], $status, [
            'Content-Type' => 'application/problem+json',
        ]);
    }
}
