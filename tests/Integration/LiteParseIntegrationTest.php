<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
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
    return __DIR__.'/../../examples/docs/apple-10k-2024.pdf';
}

it('parses a real pdf into text', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $text = Parsel::file(demoPdf())->page(1)->withoutOcr()->text();

    expect($text)->toContain('UNITED STATES');
})->group('integration');

it('parses a real pdf into a structured document with coordinates', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $document = Parsel::file(demoPdf())->page(1)->withoutOcr()->parse();

    expect($document->pageCount())->toBeGreaterThan(0)
        ->and($document->pages[0]->items)->not->toBeEmpty()
        ->and($document->pages[0]->items[0]->x)->toBeFloat();
})->group('integration');

it('streams pages of a real pdf lazily', function (): void {
    if (! litAvailable()) {
        $this->markTestSkipped('lit binary not installed');
    }

    $pages = iterator_to_array(Parsel::file(demoPdf())->pageRange(1, 2)->withoutOcr()->lazyPages());

    expect($pages)->not->toBeEmpty()
        ->and($pages[0]->items)->not->toBeEmpty()
        ->and($pages[0]->items[0]->text)->toBeString();
})->group('integration');
