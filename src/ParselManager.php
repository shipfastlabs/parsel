<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use Closure;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Drivers\AnyDocDriver;
use Shipfastlabs\Parsel\Drivers\LiteParseDriver;
use Shipfastlabs\Parsel\Exceptions\DriverNotFoundException;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\CliProcess;
use Shipfastlabs\Parsel\Support\NativeFilesystem;
use Shipfastlabs\Parsel\Support\SymfonyProcessRunner;

final class ParselManager
{
    /** @var array<string, Closure(self): Driver> */
    private array $extensions = [];

    /** @var array<string, Driver> */
    private array $drivers = [];

    public function __construct(
        private readonly ProcessRunner $process = new SymfonyProcessRunner,
        private readonly Filesystem $files = new NativeFilesystem,
        private string $default = 'liteparse',
        private readonly ?float $timeout = 60.0,
        /** @var array{liteparse?: string, anydoc?: string} */
        private readonly array $binaries = [],
    ) {}

    public function file(string $path): PendingParse
    {
        return $this->driver()->file($path);
    }

    public function bytes(string $contents, string $extension): PendingParse
    {
        return $this->driver()->bytes($contents, $extension);
    }

    public function driver(?string $name = null): Parser
    {
        return new Parser($this->resolve($name ?? $this->default), $this->files, $this->timeout);
    }

    public function defaultDriver(string $name): self
    {
        $this->default = $name;

        return $this;
    }

    /** @param Closure(self): Driver $factory */
    public function extend(string $name, Closure $factory): self
    {
        $this->extensions[$name] = $factory;
        unset($this->drivers[$name]);

        return $this;
    }

    public function forgetDrivers(): self
    {
        $this->drivers = [];

        return $this;
    }

    private function resolve(string $name): Driver
    {
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        if (isset($this->extensions[$name])) {
            return $this->drivers[$name] = ($this->extensions[$name])($this);
        }

        $driver = match ($name) {
            'liteparse' => new LiteParseDriver(
                new CliProcess($this->process, $this->files),
                new BinaryResolver(name: 'lit', envVar: 'PARSEL_LITEPARSE_BINARY'),
                $this->files,
                $this->binaries['liteparse'] ?? null,
            ),
            'anydoc' => new AnyDocDriver(
                new CliProcess($this->process, $this->files),
                new BinaryResolver(name: 'anydoc', envVar: 'PARSEL_ANYDOC_BINARY'),
                $this->binaries['anydoc'] ?? null,
            ),
            default => throw DriverNotFoundException::named($name),
        };

        return $this->drivers[$name] = $driver;
    }
}
