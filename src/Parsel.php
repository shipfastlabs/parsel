<?php

declare(strict_types=1);

namespace Shipfastlabs;

use Closure;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\ParselManager;
use Shipfastlabs\Parsel\Parser;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;
use Shipfastlabs\Parsel\Support\SymfonyProcessRunner;

final class Parsel
{
    private static ?ParselManager $manager = null;

    private static ?ProcessRunner $runner = null;

    private static string $defaultDriver = 'liteparse';

    private static ?float $timeout = 60.0;

    /** @var array<string, Closure(ParselManager): Driver> */
    private static array $extensions = [];

    public static function file(string $path): PendingParse
    {
        return self::manager()->file($path);
    }

    public static function bytes(string $contents, string $extension): PendingParse
    {
        return self::manager()->bytes($contents, $extension);
    }

    public static function driver(string $name): Parser
    {
        return self::manager()->driver($name);
    }

    public static function defaultDriver(string $name): void
    {
        self::$defaultDriver = $name;
        self::manager()->defaultDriver($name);
    }

    /** @param Closure(ParselManager): Driver $factory */
    public static function extend(string $name, Closure $factory): void
    {
        self::$extensions[$name] = $factory;
        self::manager()->extend($name, $factory);
    }

    public static function defaultTimeout(?float $seconds): void
    {
        self::$timeout = $seconds;
        self::$manager = null;
    }

    /** @param array<string, ProcessResult|string> $responses */
    public static function fake(array $responses = []): FakeProcessRunner
    {
        $fake = new FakeProcessRunner($responses);
        self::swap($fake);

        return $fake;
    }

    public static function swap(ProcessRunner $runner): void
    {
        self::$runner = $runner;
        self::$manager = null;
    }

    public static function flush(): void
    {
        self::$manager = null;
        self::$runner = null;
        self::$defaultDriver = 'liteparse';
        self::$timeout = 60.0;
        self::$extensions = [];
    }

    private static function manager(): ParselManager
    {
        if (self::$manager instanceof ParselManager) {
            return self::$manager;
        }

        $manager = new ParselManager(
            process: self::$runner ?? new SymfonyProcessRunner,
            default: self::$defaultDriver,
            timeout: self::$timeout,
            binaries: self::$runner instanceof ProcessRunner ? ['liteparse' => 'lit', 'anydoc' => 'anydoc'] : [],
        );

        foreach (self::$extensions as $name => $factory) {
            $manager->extend($name, $factory);
        }

        return self::$manager = $manager;
    }
}
