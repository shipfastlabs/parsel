<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Exceptions\PageNotFoundException;

it('maps real liteparse json into a document', function (): void {
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(fixtureContents('liteparse-output.json'), true);

    $doc = Document::fromLiteParseJson($decoded);

    expect($doc->pageCount())->toBe(2)
        ->and($doc->pages[0]->number)->toBe(1)
        ->and($doc->pages[0]->items)->toHaveCount(2)
        ->and($doc->pages[0]->items[0]->text)->toBe('UNITED STATES')
        ->and($doc->pages[0]->items[0]->fontName)->toBe('AAAGYH+HelveticaLTStd-Bold')
        ->and($doc->text)->toBe("UNITED STATES\nForm 10-K\n\nPage two body text");
});

it('tolerates a non-array pages value', function (): void {
    $doc = Document::fromLiteParseJson(['pages' => 'nope']);

    expect($doc->pages)->toBe([])
        ->and($doc->text)->toBe('');
});

it('skips pages that are not arrays', function (): void {
    $doc = Document::fromLiteParseJson(['pages' => ['bad', ['page' => 1, 'text' => 'a', 'text_items' => []]]]);

    expect($doc->pageCount())->toBe(1);
});

it('captures extra top-level keys as metadata', function (): void {
    $doc = Document::fromLiteParseJson(['pages' => [], 'version' => '2.0']);

    expect($doc->metadata)->toBe(['version' => '2.0']);
});

it('serializes to an array', function (): void {
    $doc = Document::fromLiteParseJson([
        'pages' => [['page' => 1, 'width' => 1, 'height' => 2, 'text' => 't', 'text_items' => []]],
    ]);

    expect($doc->toArray())->toHaveKeys(['pages', 'text', 'metadata'])
        ->and($doc->toArray()['pages'])->toHaveCount(1);
});

it('maps real liteparse snake_case json into a document', function (): void {
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(fixtureContents('liteparse-output-snake.json'), true);

    $doc = Document::fromLiteParseJson($decoded);

    expect($doc->pageCount())->toBe(2)
        ->and($doc->pages[0]->number)->toBe(1)
        ->and($doc->pages[0]->items)->toHaveCount(2)
        ->and($doc->pages[0]->items[0]->text)->toBe('UNITED STATES')
        ->and($doc->pages[0]->items[0]->fontName)->toBe('AAAGYH+HelveticaLTStd-Bold')
        ->and($doc->text)->toBe("UNITED STATES\nForm 10-K\n\nPage two body text");
});

it('returns a page by its 1-based number', function (): void {
    $doc = Document::fromLiteParseJson([
        'pages' => [
            ['page' => 1, 'text' => 'first', 'textItems' => []],
            ['page' => 2, 'text' => 'second', 'textItems' => []],
        ],
    ]);

    $page = $doc->page(2);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->number)->toBe(2)
        ->and($page->text)->toBe('second');
});

it('throws PageNotFoundException when the page number does not exist', function (): void {
    $doc = Document::fromLiteParseJson(['pages' => [['page' => 1, 'text' => 'a', 'textItems' => []]]]);

    $doc->page(99);
})->throws(PageNotFoundException::class, 'Page 99 was not found in the document.');

it('reports whether a page number exists', function (): void {
    $doc = Document::fromLiteParseJson(['pages' => [['page' => 1, 'text' => 'a', 'textItems' => []]]]);

    expect($doc->hasPage(1))->toBeTrue()
        ->and($doc->hasPage(99))->toBeFalse();
});

it('maps rich liteparse json produced with every extract flag', function (): void {
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(fixtureContents('liteparse-output-rich.json'), true);

    $doc = Document::fromLiteParseJson($decoded);
    $page = $doc->page(1);
    $item = $page->items[0];

    expect($item->text)->toBe('UNITED STATES')
        ->and($item->rotation)->toBe(0.0)
        ->and($item->fontWeight)->toBe(700.0)
        ->and($item->fontHeight)->toBe(13.0)
        ->and($item->fillColor)->toBe('ff000000')
        ->and($page->contentBounds?->width)->toBe(514.25)
        ->and($page->complexity)->toHaveKey('needs_ocr', true)
        ->and($page->annotations)->toBe([])
        ->and($page->formFields)->toBe([])
        ->and($page->structureTree)->toBe(['roots' => []])
        ->and($page->vectorGraphics)->toHaveKeys(['shapes', 'lines'])
        ->and($doc->images())->toHaveCount(1)
        ->and($doc->images()[0])->toHaveKey('format', 'jpg')
        ->and($doc->formType())->toBe(0)
        ->and($doc->metadata)->toHaveKeys(['images', 'form_type']);
});

it('returns no images or form type when absent or malformed', function (): void {
    $doc = Document::fromLiteParseJson(['pages' => []]);
    $malformed = Document::fromLiteParseJson(['pages' => [], 'images' => 'nope', 'form_type' => 'x']);
    $mixed = Document::fromLiteParseJson(['pages' => [], 'images' => ['bad', ['id' => 'p1_1']], 'formType' => '2']);

    expect($doc->images())->toBe([])
        ->and($doc->formType())->toBeNull()
        ->and($malformed->images())->toBe([])
        ->and($malformed->formType())->toBeNull()
        ->and($mixed->images())->toBe([['id' => 'p1_1']])
        ->and($mixed->formType())->toBe(2);
});
