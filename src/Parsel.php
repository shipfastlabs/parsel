<?php

declare(strict_types=1);

namespace Shipfastlabs;

use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Source;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;
use Shipfastlabs\Parsel\Support\SymfonyProcessRunner;

/**
 * @example $text = Parsel::file('invoice.pdf')->text();
 * @example $doc  = Parsel::file('invoice.pdf')->pageRange(1, 5)->withOcr(language: 'eng')->parse();
 */
final class Parsel
{
    private static ?string $binary = null;

    private static ?float $timeout = 60.0;

    private static ?ProcessRunner $runner = null;

    public static function file(string $path): PendingParse
    {
        return new PendingParse(
            Source::fromPath($path),
            self::runner(),
            binary: self::$binary,
            timeout: self::$timeout,
        );
    }

    public static function bytes(string $contents, string $extension): PendingParse
    {
        return new PendingParse(
            Source::fromBytes($contents, $extension),
            self::runner(),
            binary: self::$binary,
            timeout: self::$timeout,
        );
    }

    public static function usingBinary(string $path): void
    {
        self::$binary = $path;
    }

    public static function defaultTimeout(?float $seconds): void
    {
        self::$timeout = $seconds;
    }

    /**
     * @param  array<string, ProcessResult|string>  $responses
     */
    public static function fake(array $responses = []): FakeProcessRunner
    {
        self::$binary ??= 'lit';

        return self::$runner = new FakeProcessRunner($responses);
    }

    public static function swap(ProcessRunner $runner): void
    {
        self::$binary ??= 'lit';
        self::$runner = $runner;
    }

    public static function flush(): void
    {
        self::$binary = null;
        self::$timeout = 60.0;
        self::$runner = null;
    }

    private static function runner(): ProcessRunner
    {
        return self::$runner ?? new SymfonyProcessRunner;
    }
}
