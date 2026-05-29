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
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            text: Cast::str($raw['text'] ?? ''),
            x: Cast::float($raw['x'] ?? 0),
            y: Cast::float($raw['y'] ?? 0),
            width: Cast::float($raw['width'] ?? 0),
            height: Cast::float($raw['height'] ?? 0),
            confidence: isset($raw['confidence']) ? Cast::float($raw['confidence']) : null,
            fontName: isset($raw['font_name']) ? Cast::str($raw['font_name']) : null,
            fontSize: isset($raw['font_size']) ? Cast::float($raw['font_size']) : null,
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
        ];
    }
}
