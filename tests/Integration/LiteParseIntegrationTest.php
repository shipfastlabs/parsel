<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Options\LiteParseOptions;
use Shipfastlabs\Parsel\Support\BinaryResolver;

function litAvailable(): bool
{
    try {
        (new BinaryResolver)->resolve();

        return true;
    } catch (BinaryNotFoundException) {
        return false;
    }
}

function demoPdf(): string
{
    return __DIR__.'/../../examples/docs/sample.pdf';
}

it('parses a real pdf into text', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $text = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr())
        ->text();

    expect($text)->toContain('UNITED STATES');
})->group('integration');

it('parses a real pdf into markdown', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    try {
        $markdown = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr())
            ->markdown();
    } catch (ParseFailedException $parseFailedException) {
        if (str_contains($parseFailedException->stderr, "unknown format 'markdown'")) {
            $this->markTestSkipped('installed lit binary does not support Markdown output');
        }

        throw $parseFailedException;
    }

    expect($markdown)->toContain('UNITED STATES')
        ->and($markdown)->toContain('#');
})->group('integration');

it('parses a real pdf into a structured document with coordinates', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $document = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr())
        ->parse();

    expect($document->pageCount())->toBeGreaterThan(0)
        ->and($document->pages[0]->items)->not->toBeEmpty()
        ->and($document->pages[0]->items[0]->x)->toBeFloat();
})->group('integration');

it('streams pages of a real pdf lazily', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $pages = iterator_to_array(Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->pageRange(1, 2)->withoutOcr())
        ->lazyPages());

    expect($pages)->not->toBeEmpty()
        ->and($pages[0]->items)->not->toBeEmpty()
        ->and($pages[0]->items[0]->text)->toBeString();
})->group('integration');

it('screenshots a real pdf without forwarding parse-only extra options', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_shots_'.uniqid();
    mkdir($directory);

    try {
        $files = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(1)->withDpi(72)->option('extract-blocks'))
            ->screenshots($directory);

        expect($files)->not->toBeEmpty();
    } finally {
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'*') ?: []);
        rmdir($directory);
    }
})->group('integration');
