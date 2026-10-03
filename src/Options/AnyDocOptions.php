<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Options;

use Shipfastlabs\Parsel\Contracts\ProviderOptions;
use Shipfastlabs\Parsel\Enums\AnyDocOcrMode;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;

final class AnyDocOptions implements ProviderOptions
{
    /** @var array<string, mixed> */
    private array $options = [];

    /** @var array<string, string|int|bool> */
    private array $extra = [];

    public static function make(): self
    {
        return new self;
    }

    public function provider(): string
    {
        return 'anydoc';
    }

    public function format(string $format): self
    {
        $this->options['format'] = strtolower(ltrim($format, '.'));

        return $this;
    }

    public function ocr(AnyDocOcrMode|string $mode): self
    {
        if (is_string($mode)) {
            $mode = AnyDocOcrMode::tryFrom(strtolower(trim($mode)))
                ?? throw InvalidProviderOptionsException::invalidValue($this->provider(), 'ocr', $mode, AnyDocOcrMode::values());
        }

        $this->options['ocr'] = $mode->value;

        if ($mode === AnyDocOcrMode::Reject) {
            unset($this->options['api_key'], $this->options['api_url']);
        }

        return $this;
    }

    public function withHostedOcr(?string $apiKey = null, ?string $apiUrl = null): self
    {
        $this->ocr(AnyDocOcrMode::Hosted);

        foreach (['api_key' => $apiKey, 'api_url' => $apiUrl] as $key => $value) {
            if ($value !== null) {
                $this->options[$key] = $value;
            }
        }

        return $this;
    }

    public function rejectOcr(): self
    {
        return $this->ocr(AnyDocOcrMode::Reject);
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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->extra === [] ? $this->options : [...$this->options, 'extra' => $this->extra];
    }
}
