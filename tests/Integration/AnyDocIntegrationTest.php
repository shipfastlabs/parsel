<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\Exceptions\ParserUsageException;
use Shipfastlabs\Parsel\Options\AnyDocOptions;
use Shipfastlabs\Parsel\Support\BinaryResolver;

function anydocAvailable(): bool
{
    try {
        new BinaryResolver(name: 'anydoc', envVar: 'PARSEL_ANYDOC_BINARY')->resolve();

        return true;
    } catch (BinaryNotFoundException) {
        return false;
    }
}

it('converts a real document to markdown with anydoc', function (): void {
    if (! anydocAvailable()) {
        $this->markTestSkipped('anydoc binary not installed');
    }

    $markdown = Parsel::driver('anydoc')
        ->file(__DIR__.'/../../examples/docs/sample.docx')
        ->markdown();

    expect($markdown)->not->toBeEmpty();
})->group('integration');

it('reports an invalid format as a usage error', function (): void {
    if (! anydocAvailable()) {
        $this->markTestSkipped('anydoc binary not installed');
    }

    Parsel::driver('anydoc')
        ->file(__DIR__.'/../../examples/docs/sample.pdf')
        ->withProviderOptions(AnyDocOptions::make()->format('bogus'))
        ->markdown();
})->group('integration')->throws(ParserUsageException::class, "invalid format 'bogus'");
