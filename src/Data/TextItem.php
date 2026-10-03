<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

final readonly class TextItem
{
    public function __construct(
        public string $text,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?float $confidence = null,
        public ?string $fontName = null,
        public ?float $fontSize = null,
        public ?float $rotation = null,
        public ?float $fontHeight = null,
        public ?float $fontAscent = null,
        public ?float $fontDescent = null,
        public ?float $fontWeight = null,
        public ?float $textWidth = null,
        public ?string $fillColor = null,
        public ?string $strokeColor = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $fontName = Cast::pick($raw, ['fontName', 'font_name']);
        $fontSize = Cast::pick($raw, ['fontSize', 'font_size']);

        return new self(
            text: Cast::str($raw['text'] ?? ''),
            x: Cast::float($raw['x'] ?? 0),
            y: Cast::float($raw['y'] ?? 0),
            width: Cast::float($raw['width'] ?? 0),
            height: Cast::float($raw['height'] ?? 0),
            confidence: isset($raw['confidence']) ? Cast::float($raw['confidence']) : null,
            fontName: $fontName !== null ? Cast::str($fontName) : null,
            fontSize: $fontSize !== null ? Cast::float($fontSize) : null,
            rotation: Cast::nullableFloat($raw['rotation'] ?? null),
            fontHeight: Cast::nullableFloat(Cast::pick($raw, ['fontHeight', 'font_height'])),
            fontAscent: Cast::nullableFloat(Cast::pick($raw, ['fontAscent', 'font_ascent'])),
            fontDescent: Cast::nullableFloat(Cast::pick($raw, ['fontDescent', 'font_descent'])),
            fontWeight: Cast::nullableFloat(Cast::pick($raw, ['fontWeight', 'font_weight'])),
            textWidth: Cast::nullableFloat(Cast::pick($raw, ['textWidth', 'text_width'])),
            fillColor: Cast::nullableStr(Cast::pick($raw, ['fillColor', 'fill_color'])),
            strokeColor: Cast::nullableStr(Cast::pick($raw, ['strokeColor', 'stroke_color'])),
        );
    }

    /**
     * @return array{
     *     text: string,
     *     x: float,
     *     y: float,
     *     width: float,
     *     height: float,
     *     confidence: float|null,
     *     font_name: string|null,
     *     font_size: float|null,
     *     rotation: float|null,
     *     font_height: float|null,
     *     font_ascent: float|null,
     *     font_descent: float|null,
     *     font_weight: float|null,
     *     text_width: float|null,
     *     fill_color: string|null,
     *     stroke_color: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'x' => $this->x,
            'y' => $this->y,
            'width' => $this->width,
            'height' => $this->height,
            'confidence' => $this->confidence,
            'font_name' => $this->fontName,
            'font_size' => $this->fontSize,
            'rotation' => $this->rotation,
            'font_height' => $this->fontHeight,
            'font_ascent' => $this->fontAscent,
            'font_descent' => $this->fontDescent,
            'font_weight' => $this->fontWeight,
            'text_width' => $this->textWidth,
            'fill_color' => $this->fillColor,
            'stroke_color' => $this->strokeColor,
        ];
    }
}
