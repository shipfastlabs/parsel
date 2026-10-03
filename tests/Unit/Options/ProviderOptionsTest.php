<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Enums\ImageMode;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
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

it('builds OCR server header, page error and config liteparse options', function (): void {
    $options = LiteParseOptions::make()
        ->withOcr(headers: ['Authorization' => ' Bearer token '])
        ->withOcrServerHeader(' X-Tenant ', 'acme')
        ->withOcrServerHeader('X-Tenant', 'override')
        ->continueOnPageError()
        ->withConfig('/liteparse.json');

    expect($options->toArray())->toBe([
        'ocr' => true,
        'ocr_server_headers' => ['Authorization' => 'Bearer token', 'X-Tenant' => 'override'],
        'continue_on_page_error' => true,
        'config' => '/liteparse.json',
    ]);
});

it('rejects invalid OCR server headers', function (string $name, string $value): void {
    LiteParseOptions::make()->withOcrServerHeader($name, $value);
})->throws(InvalidProviderOptionsException::class, 'Invalid OCR server header')->with([
    'empty name' => ['  ', 'value'],
    'colon in name' => ['X-Key: abc', 'value'],
    'line break in name' => ["X-Key\nX-Other", 'value'],
    'line break in value' => ['X-Key', "abc\r\nX-Injected: 1"],
]);

it('builds anydoc options fluently', function (): void {
    $options = AnyDocOptions::make()->format('.CSV')->withBinary('/anydoc')->option('future', 2);

    expect($options->provider())->toBe('anydoc')
        ->and($options->toArray())->toBe([
            'format' => 'csv',
            'binary' => '/anydoc',
            'extra' => ['future' => 2],
        ]);
});
