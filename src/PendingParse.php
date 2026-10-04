<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use Generator;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\LazyPageDriver;
use Shipfastlabs\Parsel\Contracts\ProviderOptions;
use Shipfastlabs\Parsel\Contracts\ScreenshotDriver;
use Shipfastlabs\Parsel\Contracts\StructuredDocumentDriver;
use Shipfastlabs\Parsel\Contracts\TextDriver;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\UnsupportedCapabilityException;

final class PendingParse
{
    /** @var array<string, mixed> */
    private array $providerOptions = [];

    public function __construct(
        private readonly Driver $driver,
        private readonly Source $source,
        private readonly Filesystem $files,
        private ?float $timeout = ParselManager::DEFAULT_TIMEOUT,
    ) {}

    /** @param ProviderOptions|array<string, mixed> $options */
    public function withProviderOptions(ProviderOptions|array $options): self
    {
        if ($options instanceof ProviderOptions) {
            if ($options->provider() !== $this->driver->name()) {
                throw InvalidProviderOptionsException::forProvider($this->driver->name(), $options->provider());
            }

            $options = $options->toArray();
        }

        $this->driver->validateOptions($options);

        if (isset($this->providerOptions['pages'], $options['pages'])
            && is_string($this->providerOptions['pages'])
            && is_string($options['pages'])) {
            $options['pages'] = $this->providerOptions['pages'].','.$options['pages'];
        }

        foreach (['extra', 'screenshot_extra'] as $key) {
            if (isset($this->providerOptions[$key], $options[$key])
                && is_array($this->providerOptions[$key])
                && is_array($options[$key])) {
                $options[$key] = array_replace($this->providerOptions[$key], $options[$key]);
            }
        }

        $this->providerOptions = array_replace($this->providerOptions, $options);

        return $this;
    }

    public function withTimeout(?float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function markdown(): string
    {
        return $this->driver->markdown($this->request());
    }

    public function text(): string
    {
        if (! $this->driver instanceof TextDriver) {
            throw UnsupportedCapabilityException::forDriver($this->driver->name(), 'text');
        }

        return $this->driver->text($this->request());
    }

    public function parse(): Document
    {
        if (! $this->driver instanceof StructuredDocumentDriver) {
            throw UnsupportedCapabilityException::forDriver($this->driver->name(), 'structured documents');
        }

        return $this->driver->document($this->request());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->parse()->toArray();
    }

    public function save(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $contents = match ($extension) {
            'md', 'markdown' => $this->markdown(),
            'json' => $this->json(),
            default => $this->text(),
        };

        $this->files->put($path, $contents);

        return $path;
    }

    /** @return list<string> */
    public function screenshots(string $directory): array
    {
        if (! $this->driver instanceof ScreenshotDriver) {
            throw UnsupportedCapabilityException::forDriver($this->driver->name(), 'screenshots');
        }

        return $this->driver->screenshots($this->request(), $directory);
    }

    /** @return Generator<int, Page> */
    public function lazyPages(): Generator
    {
        if (! $this->driver instanceof LazyPageDriver) {
            throw UnsupportedCapabilityException::forDriver($this->driver->name(), 'lazy pages');
        }

        yield from $this->driver->pages($this->request());
    }

    private function json(): string
    {
        if (! $this->driver instanceof StructuredDocumentDriver) {
            throw UnsupportedCapabilityException::forDriver($this->driver->name(), 'JSON');
        }

        return $this->driver->json($this->request());
    }

    private function request(): ParseRequest
    {
        return new ParseRequest($this->source, $this->providerOptions, $this->timeout);
    }
}
