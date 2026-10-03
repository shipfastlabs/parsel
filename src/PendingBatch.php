<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use InvalidArgumentException;
use Shipfastlabs\Parsel\Contracts\BatchDriver;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\ProviderOptions;
use Shipfastlabs\Parsel\Enums\OutputFormat;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\UnsupportedCapabilityException;

final class PendingBatch
{
    /** @var array<string, mixed> */
    private array $providerOptions = [];

    private bool $recursive = false;

    private ?string $extension = null;

    public function __construct(
        private readonly Driver $driver,
        private readonly string $directory,
        private ?float $timeout = 60.0,
    ) {
        if ($directory === '') {
            throw new InvalidArgumentException('A non-empty input directory is required.');
        }
    }

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

        if (isset($this->providerOptions['extra'], $options['extra'])
            && is_array($this->providerOptions['extra'])
            && is_array($options['extra'])) {
            $options['extra'] = array_replace($this->providerOptions['extra'], $options['extra']);
        }

        $this->providerOptions = array_replace($this->providerOptions, $options);

        return $this;
    }

    public function withTimeout(?float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function recursive(bool $recursive = true): self
    {
        $this->recursive = $recursive;

        return $this;
    }

    public function only(string $extension): self
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($extension === '') {
            throw new InvalidArgumentException('A non-empty file extension is required.');
        }

        $this->extension = $extension;

        return $this;
    }

    /**
     * @param  string  $format  One of "markdown" (or "md"), "text" (or "txt") or "json".
     * @return list<string> The written output files.
     */
    public function saveTo(string $directory, string $format = 'markdown'): array
    {
        if (! $this->driver instanceof BatchDriver) {
            throw UnsupportedCapabilityException::forDriver($this->driver->name(), 'batch parsing');
        }

        if ($directory === '') {
            throw new InvalidArgumentException('A non-empty output directory is required.');
        }

        return $this->driver->batch(new BatchRequest(
            inputDirectory: $this->directory,
            outputDirectory: $directory,
            format: $this->format($format),
            recursive: $this->recursive,
            extension: $this->extension,
            options: $this->providerOptions,
            timeout: $this->timeout,
        ));
    }

    private function format(string $format): OutputFormat
    {
        return match (strtolower($format)) {
            'markdown', 'md' => OutputFormat::Markdown,
            'text', 'txt' => OutputFormat::Text,
            'json' => OutputFormat::Json,
            default => throw new InvalidArgumentException(sprintf('Unsupported batch output format "%s"; expected markdown, text or json.', $format)),
        };
    }
}
