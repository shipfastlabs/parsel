<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Data\TextItem;

it('maps a page with positioned text items', function (): void {
    $page = Page::fromArray([
        'page' => 3,
        'width' => 612.0,
        'height' => 792.0,
        'text' => 'hello',
        'text_items' => [
            ['text' => 'hello', 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4],
        ],
    ]);

    expect($page->number)->toBe(3)
        ->and($page->width)->toBe(612.0)
        ->and($page->height)->toBe(792.0)
        ->and($page->text)->toBe('hello')
        ->and($page->items)->toHaveCount(1)
        ->and($page->items[0])->toBeInstanceOf(TextItem::class);
});

it('tolerates a non-array text_items value', function (): void {
    expect(Page::fromArray(['page' => 1, 'text_items' => 'nope'])->items)->toBe([]);
});

it('skips text items that are not arrays', function (): void {
    $page = Page::fromArray([
        'page' => 1,
        'text_items' => ['bad', ['text' => 'a', 'x' => 1, 'y' => 1, 'width' => 1, 'height' => 1]],
    ]);

    expect($page->items)->toHaveCount(1);
});

it('serializes to an array', function (): void {
    $page = Page::fromArray(['page' => 1, 'width' => 1.0, 'height' => 2.0, 'text' => 't', 'text_items' => []]);

    expect($page->toArray())->toBe([
        'number' => 1,
        'width' => 1.0,
        'height' => 2.0,
        'text' => 't',
        'items' => [],
    ]);
});
