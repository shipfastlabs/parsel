<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

use Shipfastlabs\Parsel\Exceptions\PageNotFoundException;

final readonly class DocumentComplexity
{
    /**
     * @param  list<PageComplexity>  $pages
     */
    public function __construct(
        public array $pages,
    ) {}

    /**
     * @param  array<array-key, mixed>  $decoded
     */
    public static function fromLiteParseJson(array $decoded): self
    {
        $pages = [];

        foreach ($decoded as $rawPage) {
            if (is_array($rawPage)) {
                /** @var array<string, mixed> $rawPage */
                $pages[] = PageComplexity::fromArray($rawPage);
            }
        }

        return new self($pages);
    }

    public function needsOcr(): bool
    {
        return $this->pagesNeedingOcr() !== [];
    }

    /**
     * @return list<int>
     */
    public function pagesNeedingOcr(): array
    {
        return $this->numbers(static fn (PageComplexity $page): bool => $page->needsOcr);
    }

    public function hasComplexLayout(): bool
    {
        return $this->pagesWithComplexLayout() !== [];
    }

    /**
     * @return list<int>
     */
    public function pagesWithComplexLayout(): array
    {
        return $this->numbers(static fn (PageComplexity $page): bool => $page->hasComplexLayout());
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * @throws PageNotFoundException
     */
    public function page(int $number): PageComplexity
    {
        foreach ($this->pages as $page) {
            if ($page->number === $number) {
                return $page;
            }
        }

        throw PageNotFoundException::forNumber($number);
    }

    /**
     * @return array{needs_ocr: bool, pages_needing_ocr: list<int>, pages: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'needs_ocr' => $this->needsOcr(),
            'pages_needing_ocr' => $this->pagesNeedingOcr(),
            'pages' => array_map(static fn (PageComplexity $page): array => $page->toArray(), $this->pages),
        ];
    }

    /**
     * @param  callable(PageComplexity): bool  $filter
     * @return list<int>
     */
    private function numbers(callable $filter): array
    {
        return array_values(array_map(
            static fn (PageComplexity $page): int => $page->number,
            array_filter($this->pages, $filter),
        ));
    }
}
