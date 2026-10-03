<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\Filesystem;

final readonly class Parser
{
    public function __construct(
        private Driver $driver,
        private Filesystem $files,
        private ?float $timeout = 60.0,
    ) {}

    public function file(string $path): PendingParse
    {
        return new PendingParse($this->driver, Source::fromPath($path), $this->files, $this->timeout);
    }

    public function bytes(string $contents, string $extension): PendingParse
    {
        return new PendingParse($this->driver, Source::fromBytes($contents, $extension), $this->files, $this->timeout);
    }
}
