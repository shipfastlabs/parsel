<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\Document;

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

it('reshapes liteparse json to an array without building the object graph', function (): void {
    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(fixtureContents('liteparse-output.json'), true);

    $array = Document::arrayFromLiteParseJson($decoded);

    expect($array['pages'])->toHaveCount(2)
        ->and($array['pages'][0])->toHaveKeys(['number', 'width', 'height', 'text', 'items'])
        ->and($array['pages'][0]['items'])->toHaveCount(2)
        ->and($array['text'])->toBe("UNITED STATES\nForm 10-K\n\nPage two body text")
        ->and($array['metadata'])->toBe([]);
});

it('array reshape tolerates a non-array pages value', function (): void {
    $array = Document::arrayFromLiteParseJson(['pages' => 'nope']);

    expect($array['pages'])->toBe([])
        ->and($array['text'])->toBe('');
});

it('array reshape skips page entries that are not arrays', function (): void {
    $array = Document::arrayFromLiteParseJson(['pages' => ['bad', ['page' => 1, 'text' => 'a', 'text_items' => []]]]);

    expect($array['pages'])->toHaveCount(1);
});
