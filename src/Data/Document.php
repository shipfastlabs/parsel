<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Data;

final readonly class Document
{
    /**
     * @param  list<Page>  $pages
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $pages,
        public string $text,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $decoded
     */
    public static function fromLiteParseJson(array $decoded): self
    {
        $rawPages = $decoded['pages'] ?? [];
        $pages = [];

        if (is_array($rawPages)) {
            foreach ($rawPages as $rawPage) {
                if (is_array($rawPage)) {
                    /** @var array<string, mixed> $rawPage */
                    $pages[] = Page::fromArray($rawPage);
                }
            }
        }

        $text = implode("\n\n", array_map(static fn (Page $page): string => $page->text, $pages));

        $metadata = $decoded;
        unset($metadata['pages']);

        return new self($pages, $text, $metadata);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{pages: list<array<string, mixed>>, text: string, metadata: array<string, mixed>}
     */
    public static function arrayFromLiteParseJson(array $decoded): array
    {
        $rawPages = $decoded['pages'] ?? [];
        $pages = [];
        $texts = [];

        if (is_array($rawPages)) {
            foreach ($rawPages as $rawPage) {
                if (is_array($rawPage)) {
                    /** @var array<string, mixed> $rawPage */
                    $page = Page::fromArray($rawPage);
                    $pages[] = $page->toArray();
                    $texts[] = $page->text;
                }
            }
        }

        $metadata = $decoded;
        unset($metadata['pages']);

        return ['pages' => $pages, 'text' => implode("\n\n", $texts), 'metadata' => $metadata];
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    /**
     * @return array{pages: list<array<string, mixed>>, text: string, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'pages' => array_map(static fn (Page $page): array => $page->toArray(), $this->pages),
            'text' => $this->text,
            'metadata' => $this->metadata,
        ];
    }
}
