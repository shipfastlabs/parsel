<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Contracts;

interface Filesystem
{
    public function exists(string $path): bool;

    public function readable(string $path): bool;

    public function temporaryPath(string $extension): string;

    public function put(string $path, string $contents): void;

    public function delete(string $path): void;

    /**
     * @return list<string>
     */
    public function files(string $directory): array;
}
