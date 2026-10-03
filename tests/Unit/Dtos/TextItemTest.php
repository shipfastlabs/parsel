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
        'rotation' => null,
        'font_height' => null,
        'font_ascent' => null,
        'font_descent' => null,
        'font_weight' => null,
        'text_width' => null,
        'fill_color' => null,
        'stroke_color' => null,
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

it('maps rich text metadata from snake_case keys', function (): void {
    $item = TextItem::fromArray([
        'text' => 'Hi',
        'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0,
        'rotation' => 90,
        'font_height' => 13,
        'font_ascent' => 9.5,
        'font_descent' => -3.5,
        'font_weight' => 700,
        'text_width' => 99.5,
        'fill_color' => 'ff000000',
        'stroke_color' => 'ff111111',
    ]);

    expect($item->rotation)->toBe(90.0)
        ->and($item->fontHeight)->toBe(13.0)
        ->and($item->fontAscent)->toBe(9.5)
        ->and($item->fontDescent)->toBe(-3.5)
        ->and($item->fontWeight)->toBe(700.0)
        ->and($item->textWidth)->toBe(99.5)
        ->and($item->fillColor)->toBe('ff000000')
        ->and($item->strokeColor)->toBe('ff111111')
        ->and($item->toArray())->toMatchArray([
            'rotation' => 90.0,
            'font_weight' => 700.0,
            'fill_color' => 'ff000000',
        ]);
});

it('maps rich text metadata from camelCase keys', function (): void {
    $item = TextItem::fromArray([
        'text' => 'Hi',
        'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0,
        'fontHeight' => 12,
        'fontAscent' => 9,
        'fontDescent' => -3,
        'fontWeight' => 400,
        'textWidth' => 50,
        'fillColor' => 'ff000000',
        'strokeColor' => 'ff000000',
    ]);

    expect($item->fontHeight)->toBe(12.0)
        ->and($item->fontWeight)->toBe(400.0)
        ->and($item->textWidth)->toBe(50.0)
        ->and($item->strokeColor)->toBe('ff000000');
});

it('ignores rich metadata with unexpected types', function (): void {
    $item = TextItem::fromArray([
        'text' => 'Hi',
        'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0,
        'rotation' => 'sideways',
        'fill_color' => 123,
    ]);

    expect($item->rotation)->toBeNull()
        ->and($item->fillColor)->toBeNull()
        ->and($item->fontWeight)->toBeNull();
});
