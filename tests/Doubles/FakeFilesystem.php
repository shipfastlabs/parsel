<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Shipfastlabs\Parsel\Contracts\Filesystem;

final readonly class FakeFilesystem implements Filesystem
{
    public function __construct(
        private bool $exists = true,
        private bool $readable = true,
    ) {}

    public function exists(string $path): bool
    {
        return $this->exists;
    }

    public function readable(string $path): bool
    {
        return $this->readable;
    }

    public function temporaryPath(string $extension): string
    {
        return '/tmp/parsel-fake.'.$extension;
    }

    public function put(string $path, string $contents): void {}

    public function delete(string $path): void {}

    public function files(string $directory): array
    {
        return [];
    }
}
