<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

final readonly class Page
{
    /**
     * @param  list<TextItem>  $items
     * @param  array<array-key, mixed>|null  $complexity
     * @param  array<array-key, mixed>|null  $annotations
     * @param  array<array-key, mixed>|null  $formFields
     * @param  array<array-key, mixed>|null  $structureTree
     * @param  array<array-key, mixed>|null  $vectorGraphics
     */
    public function __construct(
        public int $number,
        public float $width,
        public float $height,
        public string $text,
        public array $items,
        public ?BoundingBox $contentBounds = null,
        public ?array $complexity = null,
        public ?array $annotations = null,
        public ?array $formFields = null,
        public ?array $structureTree = null,
        public ?array $vectorGraphics = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $rawItems = Cast::pick($raw, ['textItems', 'text_items']) ?? [];
        $items = [];

        if (is_array($rawItems)) {
            foreach ($rawItems as $rawItem) {
                if (is_array($rawItem)) {
                    /** @var array<string, mixed> $rawItem */
                    $items[] = TextItem::fromArray($rawItem);
                }
            }
        }

        $contentBounds = Cast::pick($raw, ['contentBounds', 'content_bounds']);

        return new self(
            number: Cast::int($raw['page'] ?? 0),
            width: Cast::float($raw['width'] ?? 0),
            height: Cast::float($raw['height'] ?? 0),
            text: Cast::str($raw['text'] ?? ''),
            items: $items,
            contentBounds: is_array($contentBounds) ? BoundingBox::fromArray($contentBounds) : null,
            complexity: self::optionalArray($raw, ['complexity']),
            annotations: self::optionalArray($raw, ['annotations']),
            formFields: self::optionalArray($raw, ['formFields', 'form_fields']),
            structureTree: self::optionalArray($raw, ['structureTree', 'structure_tree']),
            vectorGraphics: self::optionalArray($raw, ['vectorGraphics', 'vector_graphics']),
        );
    }

    /**
     * @return array{
     *     number: int,
     *     width: float,
     *     height: float,
     *     text: string,
     *     items: list<array<string, mixed>>,
     *     content_bounds: array{x: float, y: float, width: float, height: float}|null,
     *     complexity: array<array-key, mixed>|null,
     *     annotations: array<array-key, mixed>|null,
     *     form_fields: array<array-key, mixed>|null,
     *     structure_tree: array<array-key, mixed>|null,
     *     vector_graphics: array<array-key, mixed>|null,
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
            'content_bounds' => $this->contentBounds?->toArray(),
            'complexity' => $this->complexity,
            'annotations' => $this->annotations,
            'form_fields' => $this->formFields,
            'structure_tree' => $this->structureTree,
            'vector_graphics' => $this->vectorGraphics,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     * @return array<array-key, mixed>|null
     */
    private static function optionalArray(array $raw, array $keys): ?array
    {
        $value = Cast::pick($raw, $keys);

        return is_array($value) ? $value : null;
    }
}
