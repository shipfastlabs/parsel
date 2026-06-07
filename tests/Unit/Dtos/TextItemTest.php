<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Data\TextItem;

it('maps a full raw item including font and confidence', function (): void {
    $item = TextItem::fromArray([
        'text' => 'Hi',
        'x' => 1.5,
        'y' => 2.5,
        'width' => 3.0,
        'height' => 4.0,
        'confidence' => 0.9,
        'fontName' => 'Arial',
        'fontSize' => 12.0,
    ]);

    expect($item->text)->toBe('Hi')
        ->and($item->x)->toBe(1.5)
        ->and($item->y)->toBe(2.5)
        ->and($item->width)->toBe(3.0)
        ->and($item->height)->toBe(4.0)
        ->and($item->confidence)->toBe(0.9)
        ->and($item->fontName)->toBe('Arial')
        ->and($item->fontSize)->toBe(12.0);

    expect($item->toArray())->toBe([
        'text' => 'Hi',
        'x' => 1.5,
        'y' => 2.5,
        'width' => 3.0,
        'height' => 4.0,
        'confidence' => 0.9,
        'font_name' => 'Arial',
        'font_size' => 12.0,
    ]);
});

it('defaults optional fields to null when absent', function (): void {
    $item = TextItem::fromArray(['text' => 'x', 'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0]);

    expect($item->confidence)->toBeNull()
        ->and($item->fontName)->toBeNull()
        ->and($item->fontSize)->toBeNull();
});

it('falls back to snake_case font keys', function (): void {
    $item = TextItem::fromArray([
        'text' => 'Hi',
        'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0,
        'font_name' => 'Helvetica',
        'font_size' => 10.0,
    ]);

    expect($item->fontName)->toBe('Helvetica')
        ->and($item->fontSize)->toBe(10.0);
});
