<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Drivers;

use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Enums\AnyDocOcrMode;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\ParseRequest;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\CliArguments;
use Shipfastlabs\Parsel\Support\CliProcess;

final readonly class AnyDocDriver implements Driver
{
    private const array OPTION_KEYS = ['format', 'ocr', 'api_key', 'api_url', 'binary', 'extra'];

    public function __construct(
        private CliProcess $process = new CliProcess,
        private BinaryResolver $resolver = new BinaryResolver(name: 'anydoc', envVar: 'PARSEL_ANYDOC_BINARY'),
        private ?string $configuredBinary = null,
    ) {}

    public function name(): string
    {
        return 'anydoc';
    }

    public function validateOptions(array $options): void
    {
        $unknown = array_values(array_diff(array_keys($options), self::OPTION_KEYS));

        if ($unknown !== []) {
            throw InvalidProviderOptionsException::unknown($this->name(), $unknown);
        }

        if (array_key_exists('ocr', $options) && (! is_string($options['ocr']) || AnyDocOcrMode::tryFrom($options['ocr']) === null)) {
            throw InvalidProviderOptionsException::invalidValue($this->name(), 'ocr', $options['ocr'], AnyDocOcrMode::values());
        }
    }

    public function markdown(ParseRequest $request): string
    {
        $options = $request->options;
        $explicit = $options['binary'] ?? null;
        $binary = $this->resolver->resolve(is_string($explicit) ? $explicit : $this->configuredBinary);

        $result = $this->process->run(
            $request->source,
            function (string $file) use ($binary, $options): array {
                $command = CliArguments::command($binary, $file);

                foreach (['format' => '--format', 'ocr' => '--ocr', 'api_key' => '--api-key', 'api_url' => '--api-url'] as $key => $flag) {
                    $value = $options[$key] ?? null;

                    if (is_string($value)) {
                        $command[] = $flag;
                        $command[] = $value;
                    }
                }

                return CliArguments::appendExtra($command, $options);
            },
            $request->timeout,
            $this->name(),
        );

        return trim($result->stdout);
    }
}
