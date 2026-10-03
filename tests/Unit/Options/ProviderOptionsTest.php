<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Enums\ImageMode;
use Shipfastlabs\Parsel\Options\AnyDocOptions;
use Shipfastlabs\Parsel\Options\LiteParseOptions;

it('builds liteparse options fluently', function (): void {
    $options = LiteParseOptions::make()
        ->page(1)->pages(2, '4-5')->pageRange(7, 8)
        ->maxPages(20)->ocr()->withOcr('eng', '/tess', 'http://ocr', 4)
        ->withDpi(200)->preserveSmallText()->withPassword('pw')
        ->withImages(ImageMode::Placeholder, '/images')->withoutLinks()->keepHeadersAndFooters()
        ->withBinary('/lit')->option('future');

    expect($options->provider())->toBe('liteparse')
        ->and($options->toArray())->toMatchArray([
            'pages' => '1,2,4-5,7-8',
            'max_pages' => 20,
            'ocr' => true,
            'ocr_language' => 'eng',
            'dpi' => 200,
            'image_mode' => 'placeholder',
            'binary' => '/lit',
            'extra' => ['future' => true],
        ]);
});

it('builds disabled liteparse options without an extra bucket', function (): void {
    $options = LiteParseOptions::make()->withoutOcr()->withoutImages()->links(false)->preserveSmallText(false);

    expect($options->toArray())->toMatchArray([
        'ocr' => false,
        'image_mode' => 'off',
        'links' => false,
        'preserve_small_text' => false,
    ])->not->toHaveKey('extra');
});

it('builds liteparse JSON enrichment options', function (): void {
    expect(LiteParseOptions::make()->extractAll()->toArray())->toBe([
        'extract_blocks' => true,
        'extract_annotations' => true,
        'extract_form_fields' => true,
        'extract_structure_tree' => true,
        'extract_content_bounds' => true,
        'extract_vector_graphics' => true,
        'extract_text_metadata' => true,
        'extract_images' => true,
        'extract_xfa_packets' => true,
        'complexity' => true,
    ])->and(LiteParseOptions::make()->extractAll()->extractImages(false)->withComplexity(false)->toArray())->toMatchArray([
        'extract_blocks' => true,
        'extract_images' => false,
        'complexity' => false,
    ]);
});

it('builds anydoc options fluently', function (): void {
    $options = AnyDocOptions::make()->format('.CSV')->withBinary('/anydoc')->option('future', 2);

    expect($options->provider())->toBe('anydoc')
        ->and($options->toArray())->toBe([
            'format' => 'csv',
            'binary' => '/anydoc',
            'extra' => ['future' => 2],
        ]);
});
