<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Enums\AnyDocOcrMode;
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
        ->withBinary('/lit')->option('future')->screenshotOption('shot', 2);

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
            'screenshot_extra' => ['shot' => 2],
        ]);
});

it('builds disabled liteparse options without an extra bucket', function (): void {
    $options = LiteParseOptions::make()->withoutOcr()->withoutImages()->links(false)->preserveSmallText(false);

    expect($options->toArray())->toMatchArray([
        'ocr' => false,
        'image_mode' => 'off',
        'links' => false,
        'preserve_small_text' => false,
    ])->not->toHaveKey('extra')->not->toHaveKey('screenshot_extra');
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

it('builds anydoc hosted ocr options', function (): void {
    expect(AnyDocOptions::make()->withHostedOcr('fc-key', 'https://firecrawl.test')->toArray())->toBe([
        'ocr' => 'hosted',
        'api_key' => 'fc-key',
        'api_url' => 'https://firecrawl.test',
    ])->and(AnyDocOptions::make()->withHostedOcr()->toArray())->toBe(['ocr' => 'hosted'])
        ->and(AnyDocOptions::make()->ocr(' Hosted ')->toArray())->toBe(['ocr' => 'hosted'])
        ->and(AnyDocOptions::make()->ocr(AnyDocOcrMode::Reject)->toArray())->toBe(['ocr' => 'reject']);
});

it('drops hosted ocr credentials when switching anydoc back to reject', function (): void {
    $options = AnyDocOptions::make()->withHostedOcr('fc-key', 'https://firecrawl.test')->rejectOcr();

    expect($options->toArray())->toBe(['ocr' => 'reject']);
});

it('rejects unknown anydoc ocr modes', function (): void {
    AnyDocOptions::make()->ocr('tesseract');
})->throws(InvalidProviderOptionsException::class, "Invalid anydoc provider option [ocr]: 'tesseract'. Expected one of: reject, hosted.");
