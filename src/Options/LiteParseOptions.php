<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Options;

use Shipfastlabs\Parsel\Contracts\ProviderOptions;
use Shipfastlabs\Parsel\Enums\ImageMode;

final class LiteParseOptions implements ProviderOptions
{
    /** @var array<string, mixed> */
    private array $options = [];

    /** @var array<string, string|int|bool> */
    private array $extra = [];

    /** @var array<string, string|int|bool> */
    private array $screenshotExtra = [];

    public static function make(): self
    {
        return new self;
    }

    public function provider(): string
    {
        return 'liteparse';
    }

    public function page(int $page): self
    {
        return $this->appendPages((string) $page);
    }

    public function pages(int|string ...$pages): self
    {
        foreach ($pages as $page) {
            $this->appendPages((string) $page);
        }

        return $this;
    }

    public function pageRange(int $from, int $to): self
    {
        return $this->appendPages($from.'-'.$to);
    }

    public function maxPages(int $max): self
    {
        $this->options['max_pages'] = $max;

        return $this;
    }

    public function ocr(bool $enabled = true): self
    {
        $this->options['ocr'] = $enabled;

        return $this;
    }

    public function withOcr(
        ?string $language = null,
        ?string $tessdataPath = null,
        ?string $serverUrl = null,
        ?int $workers = null,
    ): self {
        $this->options['ocr'] = true;

        foreach (['ocr_language' => $language, 'tessdata_path' => $tessdataPath, 'ocr_server_url' => $serverUrl, 'workers' => $workers] as $key => $value) {
            if ($value !== null) {
                $this->options[$key] = $value;
            }
        }

        return $this;
    }

    public function withoutOcr(): self
    {
        return $this->ocr(false);
    }

    public function withDpi(int $dpi): self
    {
        $this->options['dpi'] = $dpi;

        return $this;
    }

    public function preserveSmallText(bool $preserve = true): self
    {
        $this->options['preserve_small_text'] = $preserve;

        return $this;
    }

    public function withPassword(string $password): self
    {
        $this->options['password'] = $password;

        return $this;
    }

    public function withImages(ImageMode|string $mode = ImageMode::Placeholder, ?string $directory = null): self
    {
        $this->options['image_mode'] = is_string($mode) ? ImageMode::from($mode)->value : $mode->value;

        if ($directory !== null) {
            $this->options['image_directory'] = $directory;
        }

        return $this;
    }

    public function withoutImages(): self
    {
        return $this->withImages(ImageMode::Off);
    }

    public function links(bool $enabled = true): self
    {
        $this->options['links'] = $enabled;

        return $this;
    }

    public function withoutLinks(): self
    {
        return $this->links(false);
    }

    public function keepHeadersAndFooters(bool $keep = true): self
    {
        $this->options['keep_headers_and_footers'] = $keep;

        return $this;
    }

    public function withBinary(string $path): self
    {
        $this->options['binary'] = $path;

        return $this;
    }

    public function option(string $name, string|int|bool $value = true): self
    {
        $this->extra[$name] = $value;

        return $this;
    }

    public function screenshotOption(string $name, string|int|bool $value = true): self
    {
        $this->screenshotExtra[$name] = $value;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $options = $this->options;

        if ($this->extra !== []) {
            $options['extra'] = $this->extra;
        }

        if ($this->screenshotExtra !== []) {
            $options['screenshot_extra'] = $this->screenshotExtra;
        }

        return $options;
    }

    private function appendPages(string $fragment): self
    {
        $pages = $this->options['pages'] ?? null;
        $this->options['pages'] = is_string($pages) ? $pages.','.$fragment : $fragment;

        return $this;
    }
}
