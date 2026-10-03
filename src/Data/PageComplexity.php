<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

final readonly class PageComplexity
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $number,
        public bool $needsOcr,
        public array $reasons,
        public int $textLength,
        public float $textCoverage,
        public bool $hasSubstantialImages,
        public int $imageBlockCount,
        public float $imageCoverage,
        public float $largestImageCoverage,
        public bool $fullPageImage,
        public bool $isGarbled,
        public float $pageArea,
        public ?float $uncoveredVectorArea = null,
        public ?LayoutComplexity $layout = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $layout = $raw['layout'] ?? null;

        /** @var array<string, mixed>|null $layout */
        $layout = is_array($layout) ? $layout : null;

        return new self(
            number: Cast::int($raw['pageNumber'] ?? 0),
            needsOcr: Cast::bool($raw['needsOcr'] ?? false),
            reasons: Cast::strings($raw['reasons'] ?? []),
            textLength: Cast::int($raw['textLength'] ?? 0),
            textCoverage: Cast::float($raw['textCoverage'] ?? 0),
            hasSubstantialImages: Cast::bool($raw['hasSubstantialImages'] ?? false),
            imageBlockCount: Cast::int($raw['imageBlockCount'] ?? 0),
            imageCoverage: Cast::float($raw['imageCoverage'] ?? 0),
            largestImageCoverage: Cast::float($raw['largestImageCoverage'] ?? 0),
            fullPageImage: Cast::bool($raw['fullPageImage'] ?? false),
            isGarbled: Cast::bool($raw['isGarbled'] ?? false),
            pageArea: Cast::float($raw['pageArea'] ?? 0),
            uncoveredVectorArea: isset($raw['uncoveredVectorArea']) ? Cast::float($raw['uncoveredVectorArea']) : null,
            layout: $layout !== null ? LayoutComplexity::fromArray($layout) : null,
        );
    }

    public function hasComplexLayout(): bool
    {
        return $this->layout instanceof LayoutComplexity && $this->layout->isComplex;
    }

    /**
     * @return array{
     *     number: int,
     *     needs_ocr: bool,
     *     reasons: list<string>,
     *     text_length: int,
     *     text_coverage: float,
     *     has_substantial_images: bool,
     *     image_block_count: int,
     *     image_coverage: float,
     *     largest_image_coverage: float,
     *     full_page_image: bool,
     *     is_garbled: bool,
     *     page_area: float,
     *     uncovered_vector_area: float|null,
     *     layout: array<string, mixed>|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'needs_ocr' => $this->needsOcr,
            'reasons' => $this->reasons,
            'text_length' => $this->textLength,
            'text_coverage' => $this->textCoverage,
            'has_substantial_images' => $this->hasSubstantialImages,
            'image_block_count' => $this->imageBlockCount,
            'image_coverage' => $this->imageCoverage,
            'largest_image_coverage' => $this->largestImageCoverage,
            'full_page_image' => $this->fullPageImage,
            'is_garbled' => $this->isGarbled,
            'page_area' => $this->pageArea,
            'uncovered_vector_area' => $this->uncoveredVectorArea,
            'layout' => $this->layout?->toArray(),
        ];
    }
}
