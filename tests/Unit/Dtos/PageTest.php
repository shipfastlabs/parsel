<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\BoundingBox;
use Shipfastlabs\Parsel\Data\Page;

it('maps a page with positioned text items', function (): void {
    $page = Page::fromArray([
        'page' => 3,
        'width' => 612.0,
        'height' => 792.0,
        'text' => 'hello',
        'textItems' => [
            ['text' => 'hello', 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4],
        ],
    ]);

    expect($page->number)->toBe(3)
        ->and($page->width)->toBe(612.0)
        ->and($page->height)->toBe(792.0)
        ->and($page->text)->toBe('hello')
        ->and($page->items)->toHaveCount(1)
        ->and($page->items[0]->text)->toBe('hello');
});

it('tolerates a non-array textItems value', function (): void {
    expect(Page::fromArray(['page' => 1, 'textItems' => 'nope'])->items)->toBe([]);
});

it('falls back to snake_case text_items key', function (): void {
    $page = Page::fromArray([
        'page' => 1,
        'text_items' => [
            ['text' => 'hello', 'x' => 1, 'y' => 2, 'width' => 3, 'height' => 4],
        ],
    ]);

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->x)->toBe(1.0);
});

it('skips text items that are not arrays', function (): void {
    $page = Page::fromArray([
        'page' => 1,
        'textItems' => ['bad', ['text' => 'a', 'x' => 1, 'y' => 1, 'width' => 1, 'height' => 1]],
    ]);

    expect($page->items)->toHaveCount(1);
});

it('serializes to an array', function (): void {
    $page = Page::fromArray(['page' => 1, 'width' => 1.0, 'height' => 2.0, 'text' => 't', 'textItems' => []]);

    expect($page->toArray())->toBe([
        'number' => 1,
        'width' => 1.0,
        'height' => 2.0,
        'text' => 't',
        'items' => [],
        'content_bounds' => null,
        'complexity' => null,
        'annotations' => null,
        'form_fields' => null,
        'structure_tree' => null,
        'vector_graphics' => null,
    ]);
});

it('leaves rich page fields null when absent', function (): void {
    $page = Page::fromArray(['page' => 1, 'text_items' => []]);

    expect($page->contentBounds)->toBeNull()
        ->and($page->complexity)->toBeNull()
        ->and($page->annotations)->toBeNull()
        ->and($page->formFields)->toBeNull()
        ->and($page->structureTree)->toBeNull()
        ->and($page->vectorGraphics)->toBeNull();
});

it('maps rich page fields from snake_case keys', function (): void {
    $page = Page::fromArray([
        'page' => 1,
        'content_bounds' => ['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4],
        'complexity' => ['needs_ocr' => true],
        'annotations' => [['subtype' => 'Link', 'uri' => 'https://example.com']],
        'form_fields' => [['id' => 'f1', 'type' => 'text']],
        'structure_tree' => ['roots' => []],
        'vector_graphics' => ['shapes' => [], 'lines' => []],
    ]);

    expect($page->contentBounds)->toEqual(new BoundingBox(1.0, 2.0, 3.0, 4.0))
        ->and($page->complexity)->toBe(['needs_ocr' => true])
        ->and($page->annotations)->toBe([['subtype' => 'Link', 'uri' => 'https://example.com']])
        ->and($page->formFields)->toBe([['id' => 'f1', 'type' => 'text']])
        ->and($page->structureTree)->toBe(['roots' => []])
        ->and($page->vectorGraphics)->toBe(['shapes' => [], 'lines' => []])
        ->and($page->toArray())->toMatchArray([
            'content_bounds' => ['x' => 1.0, 'y' => 2.0, 'width' => 3.0, 'height' => 4.0],
            'form_fields' => [['id' => 'f1', 'type' => 'text']],
        ]);
});

it('maps rich page fields from camelCase keys', function (): void {
    $page = Page::fromArray([
        'page' => 1,
        'contentBounds' => ['x' => 5, 'y' => 6, 'width' => 7, 'height' => 8],
        'formFields' => [],
        'structureTree' => ['roots' => []],
        'vectorGraphics' => ['shapes' => []],
    ]);

    expect($page->contentBounds?->x)->toBe(5.0)
        ->and($page->formFields)->toBe([])
        ->and($page->structureTree)->toBe(['roots' => []])
        ->and($page->vectorGraphics)->toBe(['shapes' => []]);
});

it('ignores rich page fields that are not arrays', function (): void {
    $page = Page::fromArray(['page' => 1, 'content_bounds' => 'nope', 'complexity' => 'nope']);

    expect($page->contentBounds)->toBeNull()
        ->and($page->complexity)->toBeNull();
});
