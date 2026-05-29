<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

final readonly class Page
{
    /**
     * @param  list<TextItem>  $items
     */
    public function __construct(
        public int $number,
        public float $width,
        public float $height,
        public string $text,
        public array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $rawItems = $raw['text_items'] ?? [];
        $items = [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $rawItem) {
                if (is_array($rawItem)) {
                    /** @var array<string, mixed> $rawItem */
                    $items[] = TextItem::fromArray($rawItem);
                }
            }
        }

        return new self(
            number: Cast::int($raw['page'] ?? 0),
            width: Cast::float($raw['width'] ?? 0),
            height: Cast::float($raw['height'] ?? 0),
            text: Cast::str($raw['text'] ?? ''),
            items: $items,
        );
    }

    /**
     * @return array{
     *     number: int,
     *     width: float,
     *     height: float,
     *     text: string,
     *     items: list<array<string, mixed>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'width' => $this->width,
            'height' => $this->height,
            'text' => $this->text,
            'items' => array_map(static fn (TextItem $item): array => $item->toArray(), $this->items),
        ];
    }
}
