<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Drivers;

use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Enums\AnyDocOcrMode;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\OcrRequiredException;
use Shipfastlabs\Parsel\Exceptions\ParserUsageException;
use Shipfastlabs\Parsel\ParseRequest;
use Shipfastlabs\Parsel\Source;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\CliArguments;
use Shipfastlabs\Parsel\Support\CliProcess;

final readonly class AnyDocDriver implements Driver
{
    private const array OPTION_KEYS = ['format', 'ocr', 'api_key', 'api_url', 'binary', 'extra'];

    private const array FAILURES = [
        2 => ParserUsageException::class,
        3 => OcrRequiredException::class,
    ];

    private const array STDIN_EXTENSIONS = [
        'csv', 'doc', 'docm', 'docx', 'epub', 'ods', 'odp', 'odt', 'pdf', 'pot', 'pps', 'ppsm',
        'ppsx', 'ppt', 'pptm', 'pptx', 'rtf', 'xls', 'xlsb', 'xlsm', 'xlsx',
    ];

    private const array SIGNATURELESS_EXTENSIONS = ['csv'];

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
        $binary = $this->resolver->resolve(CliArguments::string($options, 'binary') ?? $this->configuredBinary);
        $format = CliArguments::string($options, 'format');
        $source = $request->source;

        $result = $this->streamsThroughStdin($source, $format)
            ? $this->process->runWithInput(
                $this->command($binary, '-', $format ?? $this->stdinFormat($source), $options),
                $source->contents(),
                $request->timeout,
                $this->name(),
                self::FAILURES,
            )
            : $this->process->run(
                $source,
                fn (string $file): array => $this->command($binary, $file, $format, $options),
                $request->timeout,
                $this->name(),
                self::FAILURES,
            );

        return trim($result->stdout);
    }

    private function streamsThroughStdin(Source $source, ?string $format): bool
    {
        return $source->isBytes()
            && ($format !== null || in_array($source->extension, self::STDIN_EXTENSIONS, true));
    }

    private function stdinFormat(Source $source): ?string
    {
        return in_array($source->extension, self::SIGNATURELESS_EXTENSIONS, true) ? $source->extension : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function command(string $binary, string $input, ?string $format, array $options): array
    {
        $command = CliArguments::command($binary, $input);

        $command = CliArguments::flag($command, 'format', $format);
        $command = CliArguments::flag($command, 'ocr', CliArguments::string($options, 'ocr'));

        if (($options['ocr'] ?? null) !== AnyDocOcrMode::Reject->value) {
            $command = CliArguments::flag($command, 'api-key', CliArguments::string($options, 'api_key'));
            $command = CliArguments::flag($command, 'api-url', CliArguments::string($options, 'api_url'));
        }

        return CliArguments::appendExtra($command, $options);
    }
}
