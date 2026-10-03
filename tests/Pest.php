<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\ParselManager;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;

uses()
    ->beforeEach(function (): void {
        putenv('PARSEL_LIT_BINARY');
        putenv('PARSEL_LITEPARSE_BINARY');
        putenv('PARSEL_ANYDOC_BINARY');
        Parsel::flush();
    })
    ->afterEach(function (): void {
        putenv('PARSEL_LIT_BINARY');
        putenv('PARSEL_LITEPARSE_BINARY');
        putenv('PARSEL_ANYDOC_BINARY');
        Parsel::flush();
    })
    ->in('Unit');

function fixtureContents(string $name): string
{
    return (string) file_get_contents(fixture($name));
}

function fakeParse(FakeProcessRunner $runner, string $binary = 'lit'): PendingParse
{
    return new ParselManager(process: $runner, binaries: ['liteparse' => $binary])
        ->file(fixture('sample.pdf'))
        ->withProviderOptions([]);
}

function requireBinary(TestCase $test, string $name, string $envVar): string
{
    try {
        return new BinaryResolver(name: $name, envVar: $envVar)->resolve();
    } catch (BinaryNotFoundException $binaryNotFoundException) {
        if (filter_var(getenv('PARSEL_REQUIRE_BINARIES'), FILTER_VALIDATE_BOOL)) {
            Assert::fail('PARSEL_REQUIRE_BINARIES is set but '.$binaryNotFoundException->getMessage());
        }

        $test->markTestSkipped($name.' binary not installed');
    }
}

function requireExtendedIntegration(TestCase $test, string $reason): void
{
    if (! filter_var(getenv('PARSEL_INTEGRATION_EXTENDED'), FILTER_VALIDATE_BOOL)) {
        $test->markTestSkipped($reason.' (set PARSEL_INTEGRATION_EXTENDED=1 to run)');
    }
}
