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

it('returns only the screenshots a real run produced', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_shots_'.uniqid();
    mkdir($directory);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'.gitkeep', '');
    file_put_contents($directory.DIRECTORY_SEPARATOR.'page_99.png', 'stale');

    try {
        $first = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->pages(2, 10)->withDpi(20))
            ->screenshots($directory);

        $second = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(2)->withDpi(20))
            ->screenshots($directory);

        expect($first)->toBe([
            $directory.DIRECTORY_SEPARATOR.'page_2.png',
            $directory.DIRECTORY_SEPARATOR.'page_10.png',
        ])->and($second)->toBe([$directory.DIRECTORY_SEPARATOR.'page_2.png'])
            ->and(file_get_contents($directory.DIRECTORY_SEPARATOR.'page_99.png'))->toBe('stale')
            ->and(file_exists($directory.DIRECTORY_SEPARATOR.'.gitkeep'))->toBeTrue();
    } finally {
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'{,.}[!.]*', GLOB_BRACE) ?: []);
        rmdir($directory);
    }
})->group('integration');
