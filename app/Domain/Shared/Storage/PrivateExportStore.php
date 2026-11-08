<?php

declare(strict_types=1);

namespace App\Domain\Shared\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Private disk for future roster exports. This slice does not render files.
 * A single server can use the local disk. More than one app server needs a
 * shared private disk such as S3-compatible object storage.
 */
final class PrivateExportStore
{
    public function disk(): Filesystem
    {
        return Storage::disk('exports');
    }

    public function put(string $path, string $contents): void
    {
        $this->disk()->put($path, $contents);
    }
}
