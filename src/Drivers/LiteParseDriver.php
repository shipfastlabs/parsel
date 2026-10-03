<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Drivers;

use Generator;
use JsonException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\LazyPageDriver;
use Shipfastlabs\Parsel\Contracts\ScreenshotDriver;
use Shipfastlabs\Parsel\Contracts\StructuredDocumentDriver;
use Shipfastlabs\Parsel\Contracts\TextDriver;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Enums\OutputFormat;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Exceptions\InvalidOutputException;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\ParseRequest;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\CliArguments;
use Shipfastlabs\Parsel\Support\CliProcess;
use Shipfastlabs\Parsel\Support\NativeFilesystem;

final readonly class LiteParseDriver implements Driver, LazyPageDriver, ScreenshotDriver, StructuredDocumentDriver, TextDriver
{
    private const array OPTION_KEYS = [
        'pages', 'max_pages', 'ocr', 'ocr_language', 'ocr_server_url', 'tessdata_path', 'workers', 'dpi',
        'preserve_small_text', 'password', 'image_mode', 'image_directory', 'links',
        'keep_headers_and_footers', 'binary', 'extra',
    ];

    public function __construct(
        private CliProcess $process = new CliProcess,
        private BinaryResolver $resolver = new BinaryResolver,
        private Filesystem $files = new NativeFilesystem,
        private ?string $configuredBinary = null,
    ) {}

    public function name(): string
    {
        return 'liteparse';
    }

    public function validateOptions(array $options): void
    {
        $unknown = array_values(array_diff(array_keys($options), self::OPTION_KEYS));

        if ($unknown !== []) {
            throw InvalidProviderOptionsException::unknown($this->name(), $unknown);
        }
    }

    public function markdown(ParseRequest $request): string
    {
        return trim($this->parseResult($request, OutputFormat::Markdown));
    }

    public function text(ParseRequest $request): string
    {
        $text = $this->parseResult($request, OutputFormat::Text);
        $stripped = preg_replace('/^--- Page \d+ ---\R?/m', '', $text) ?? $text;

        return trim($stripped);
    }

    public function document(ParseRequest $request): Document
    {
        return Document::fromLiteParseJson($this->decodeJson($this->json($request)));
    }

    public function json(ParseRequest $request): string
    {
        return trim($this->parseResult($request, OutputFormat::Json));
    }

    public function screenshots(ParseRequest $request, string $directory): array
    {
        if (! $this->files->exists($directory)) {
            throw FilesystemException::directoryNotFound($directory);
        }

        $options = $request->options;
        $binary = $this->resolver->resolve($this->string($options, 'binary') ?? $this->configuredBinary);
        $before = $this->screenshotFiles($directory);
        $startedAt = time();

        $this->process->run(
            $request->source,
            function (string $file) use ($binary, $directory, $options): array {
                $command = CliArguments::command($binary, 'screenshot', $file, '-o', $directory, '-q');
                $command = $this->appendFlag($command, 'target-pages', $this->scalar($options, 'pages'));
                $command = $this->appendFlag($command, 'dpi', $this->scalar($options, 'dpi'));
                $command = $this->appendFlag($command, 'password', $this->scalar($options, 'password'));

                return CliArguments::appendExtra($command, $options);
            },
            $request->timeout,
            $this->name(),
        );

        return $this->producedScreenshots($before, $this->screenshotFiles($directory), $startedAt, $this->targetPages($options));
    }

    public function pages(ParseRequest $request): Generator
    {
        $output = $this->files->temporaryPath('json');
        $options = $request->options;
        $binary = $this->resolver->resolve($this->string($options, 'binary') ?? $this->configuredBinary);

        try {
            $this->process->run(
                $request->source,
                fn (string $file): array => $this->parseArgv($binary, $file, OutputFormat::Json, $options, $output),
                $request->timeout,
                $this->name(),
            );

            foreach (Items::fromFile($output, ['pointer' => '/pages', 'decoder' => new ExtJsonDecoder(true)]) as $rawPage) {
                if (is_array($rawPage)) {
                    /** @var array<string, mixed> $rawPage */
                    yield Page::fromArray($rawPage);
                }
            }
        } finally {
            $this->files->delete($output);
        }
    }

    private function parseResult(ParseRequest $request, OutputFormat $format): string
    {
        $options = $request->options;
        $binary = $this->resolver->resolve($this->string($options, 'binary') ?? $this->configuredBinary);

        return $this->process->run(
            $request->source,
            fn (string $file): array => $this->parseArgv($binary, $file, $format, $options),
            $request->timeout,
            $this->name(),
        )->stdout;
    }

    /**
     * @param  array<string, array{page: int, modified: int|null}>  $before
     * @param  array<string, array{page: int, modified: int|null}>  $after
     * @param  array<int, true>|null  $targetPages
     * @return list<string>
     */
    private function producedScreenshots(array $before, array $after, int $startedAt, ?array $targetPages): array
    {
        $produced = [];

        foreach ($after as $path => ['page' => $page, 'modified' => $modified]) {
            $previous = $before[$path] ?? null;

            if ($previous === null
                || $previous['modified'] !== $modified
                || ($modified !== null && $modified >= $startedAt && ($targetPages === null || isset($targetPages[$page])))) {
                $produced[$page] = $path;
            }
        }

        ksort($produced);

        return array_values($produced);
    }

    /**
     * @return array<string, array{page: int, modified: int|null}>
     */
    private function screenshotFiles(string $directory): array
    {
        $screenshots = [];

        foreach ($this->files->files($directory) as $path) {
            if (preg_match('/^page_([1-9]\d*)\.png$/', basename($path), $matches) === 1) {
                clearstatcache(true, $path);
                $modified = @filemtime($path);
                $screenshots[$path] = ['page' => (int) $matches[1], 'modified' => $modified === false ? null : $modified];
            }
        }

        return $screenshots;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, true>|null
     */
    private function targetPages(array $options): ?array
    {
        $value = $this->scalar($options, 'pages');

        if ($value === null) {
            return null;
        }

        $pages = [];

        foreach (explode(',', (string) $value) as $part) {
            $bounds = array_map(intval(...), explode('-', trim($part), 2));
            $pages += array_fill_keys(range($bounds[0], $bounds[1] ?? $bounds[0]), true);
        }

        return $pages;
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $json): array
    {
        if ($json === '') {
            throw InvalidOutputException::emptyOutput($this->name());
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidOutputException::malformedJson($jsonException->getMessage(), $this->name());
        }

        if (! is_array($decoded)) {
            throw InvalidOutputException::malformedJson('expected a JSON object', $this->name());
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function parseArgv(string $binary, string $file, OutputFormat $format, array $options, ?string $output = null): array
    {
        $command = CliArguments::command($binary, 'parse', $file, '--format', $format->value, '-q');

        if ($output !== null) {
            $command[] = '-o';
            $command[] = $output;
        }

        $command = $this->appendFlag($command, 'target-pages', $this->scalar($options, 'pages'));
        $command = $this->appendFlag($command, 'max-pages', $this->scalar($options, 'max_pages'));
        $command = $this->appendFlag($command, 'password', $this->scalar($options, 'password'));

        if (($options['ocr'] ?? false) !== true) {
            $command[] = '--no-ocr';
        } else {
            $command = $this->appendFlag($command, 'ocr-language', $this->scalar($options, 'ocr_language'));
            $command = $this->appendFlag($command, 'ocr-server-url', $this->scalar($options, 'ocr_server_url'));
            $command = $this->appendFlag($command, 'tessdata-path', $this->scalar($options, 'tessdata_path'));
            $command = $this->appendFlag($command, 'num-workers', $this->scalar($options, 'workers'));
        }

        $command = $this->appendFlag($command, 'dpi', $this->scalar($options, 'dpi'));

        if (($options['preserve_small_text'] ?? false) === true) {
            $command[] = '--preserve-small-text';
        }

        if ($format === OutputFormat::Markdown) {
            $command = $this->appendFlag($command, 'image-mode', $this->scalar($options, 'image_mode'));
            $command = $this->appendFlag($command, 'image-output-dir', $this->scalar($options, 'image_directory'));

            if (($options['links'] ?? true) === false) {
                $command[] = '--no-links';
            }

            if (($options['keep_headers_and_footers'] ?? false) === true) {
                $command[] = '--keep-headers-footers';
            }
        }

        return CliArguments::appendExtra($command, $options);
    }

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    private function appendFlag(array $command, string $name, string|int|null $value): array
    {
        if ($value !== null) {
            $command[] = '--'.$name;
            $command[] = (string) $value;
        }

        return $command;
    }

    /** @param array<string, mixed> $options */
    private function scalar(array $options, string $key): string|int|null
    {
        $value = $options[$key] ?? null;

        return is_string($value) || is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $options */
    private function string(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
