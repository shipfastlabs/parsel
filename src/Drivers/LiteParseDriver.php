<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Drivers;

use Generator;
use JsonException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Shipfastlabs\Parsel\Contracts\ComplexityDriver;
use Shipfastlabs\Parsel\Contracts\Driver;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\LazyPageDriver;
use Shipfastlabs\Parsel\Contracts\ScreenshotDriver;
use Shipfastlabs\Parsel\Contracts\StructuredDocumentDriver;
use Shipfastlabs\Parsel\Contracts\TextDriver;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\DocumentComplexity;
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
use Shipfastlabs\Parsel\Support\ProcessResult;
use Shipfastlabs\Parsel\Support\StagingDirectory;

final readonly class LiteParseDriver implements ComplexityDriver, Driver, LazyPageDriver, ScreenshotDriver, StructuredDocumentDriver, TextDriver
{
    private const array OPTION_KEYS = [
        'pages', 'max_pages', 'ocr', 'ocr_language', 'ocr_server_url', 'tessdata_path', 'workers', 'dpi',
        'preserve_small_text', 'password', 'image_mode', 'image_directory', 'links',
        'keep_headers_and_footers', 'ocr_server_headers', 'continue_on_page_error', 'config', 'binary', 'extra',
        'screenshot_extra',
        'extract_blocks', 'extract_annotations', 'extract_form_fields', 'extract_structure_tree',
        'extract_content_bounds', 'extract_vector_graphics', 'extract_text_metadata', 'extract_images',
        'extract_xfa_packets', 'complexity',
    ];

    private const array JSON_FLAGS = [
        'extract_blocks' => '--extract-blocks',
        'extract_annotations' => '--extract-annotations',
        'extract_form_fields' => '--extract-form-fields',
        'extract_structure_tree' => '--extract-structure-tree',
        'extract_content_bounds' => '--extract-content-bounds',
        'extract_vector_graphics' => '--extract-vector-graphics',
        'extract_text_metadata' => '--extract-text-metadata',
        'extract_images' => '--extract-images',
        'extract_xfa_packets' => '--extract-xfa-packets',
        'complexity' => '--complexity',
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
        return $this->normalizeText($this->parseResult($request, OutputFormat::Text));
    }

    public function document(ParseRequest $request): Document
    {
        /** @var array<string, mixed> $decoded */
        $decoded = $this->decodeJson($this->json($request));

        return Document::fromLiteParseJson($decoded);
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
        $staging = new StagingDirectory($this->files);
        $output = $staging->create();

        try {
            $this->process->run(
                $request->source,
                function (string $file) use ($binary, $output, $options): array {
                    $command = CliArguments::command($binary, 'screenshot', $file, '-o', $output, '-q');
                    $command = $this->appendFlag($command, 'target-pages', $this->scalar($options, 'pages'));
                    $command = $this->appendFlag($command, 'dpi', $this->scalar($options, 'dpi'));
                    $command = $this->appendFlag($command, 'password', $this->scalar($options, 'password'));

                    return CliArguments::appendExtra($command, $options, 'screenshot_extra');
                },
                $request->timeout,
                $this->name(),
            );

            $screenshots = [];

            foreach ($this->files->files($output) as $path) {
                if (preg_match('/^page_([1-9]\d*)\.png$/', basename($path), $matches) === 1) {
                    $destination = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.basename($path);
                    $staging->move($path, $destination);
                    $screenshots[(int) $matches[1]] = $destination;
                }
            }

            ksort($screenshots);

            return array_values($screenshots);
        } finally {
            $staging->delete($output);
        }
    }

    public function complexity(ParseRequest $request): DocumentComplexity
    {
        $options = $request->options;
        $binary = $this->resolver->resolve($this->string($options, 'binary') ?? $this->configuredBinary);

        $result = $this->process->run(
            $request->source,
            function (string $file) use ($binary, $options): array {
                $command = CliArguments::command($binary, 'is-complex', $file, '--compact', '-q');
                $command = $this->appendFlag($command, 'target-pages', $this->scalar($options, 'pages'));
                $command = $this->appendFlag($command, 'max-pages', $this->scalar($options, 'max_pages'));
                $command = $this->appendFlag($command, 'password', $this->scalar($options, 'password'));

                return CliArguments::appendExtra($command, $options);
            },
            $request->timeout,
            $this->name(),
            accepts: $this->reportedPagesNeedingOcr(...),
        );

        $decoded = $this->decodeJson(trim($result->stdout));

        if (! array_is_list($decoded)) {
            throw InvalidOutputException::malformedJson('expected a JSON array', $this->name());
        }

        return DocumentComplexity::fromLiteParseJson($decoded);
    }

    private function reportedPagesNeedingOcr(ProcessResult $result): bool
    {
        return $result->exitCode === 1 && str_starts_with(ltrim($result->stdout), '[');
    }

    public function pages(ParseRequest $request): Generator
    {
        $output = $this->files->temporaryPath('json');
        $options = $request->options;
        $binary = $this->resolver->resolve($this->string($options, 'binary') ?? $this->configuredBinary);
        $config = $this->writeConfig($options);

        try {
            $this->process->run(
                $request->source,
                fn (string $file): array => $this->parseArgv($binary, $file, OutputFormat::Json, $options, $output, $config),
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
            $this->deleteConfig($config);
        }
    }

    private function parseResult(ParseRequest $request, OutputFormat $format): string
    {
        $options = $request->options;
        $binary = $this->resolver->resolve($this->string($options, 'binary') ?? $this->configuredBinary);
        $config = $this->writeConfig($options);

        try {
            return $this->process->run(
                $request->source,
                fn (string $file): array => $this->parseArgv($binary, $file, $format, $options, config: $config),
                $request->timeout,
                $this->name(),
            )->stdout;
        } finally {
            $this->deleteConfig($config);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function writeConfig(array $options): ?string
    {
        $tessdata = $this->string($options, 'tessdata_path');

        if (($options['ocr'] ?? false) !== true || $tessdata === null) {
            return null;
        }

        $settings = $this->userConfig($this->string($options, 'config'));

        if ($settings === null) {
            return null;
        }

        $path = $this->files->temporaryPath('json');
        $this->files->put($path, json_encode([...$settings, 'tessdataPath' => $tessdata], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function userConfig(?string $path): ?array
    {
        if ($path === null) {
            return [];
        }

        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    private function deleteConfig(?string $config): void
    {
        if ($config !== null) {
            $this->files->delete($config);
        }
    }

    private function normalizeText(string $text): string
    {
        if (preg_match('/\A\s*--- Page \d+ ---/', $text) === 1) {
            $text = preg_replace('/^--- Page \d+ ---\R?/m', '', $text) ?? $text;
        }

        $text = preg_replace('/\R*\f\R*/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /** @return array<array-key, mixed> */
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

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function parseArgv(string $binary, string $file, OutputFormat $format, array $options, ?string $output = null, ?string $config = null): array
    {
        $command = CliArguments::command($binary, 'parse', $file, '--format', $format->value, '-q');

        if ($output !== null) {
            $command[] = '-o';
            $command[] = $output;
        }

        $command = $this->appendFlag($command, 'target-pages', $this->scalar($options, 'pages'));
        $command = $this->appendFlag($command, 'max-pages', $this->scalar($options, 'max_pages'));
        $command = $this->appendFlag($command, 'password', $this->scalar($options, 'password'));
        $command = $this->appendFlag($command, 'config', $config ?? $this->string($options, 'config'));

        if (($options['continue_on_page_error'] ?? false) === true) {
            $command[] = '--continue-on-page-error';
        }

        if (($options['ocr'] ?? false) !== true) {
            $command[] = '--no-ocr';
        } else {
            $command = $this->appendFlag($command, 'ocr-language', $this->scalar($options, 'ocr_language'));
            $command = $this->appendFlag($command, 'ocr-server-url', $this->scalar($options, 'ocr_server_url'));
            $command = $this->appendOcrServerHeaders($command, $options['ocr_server_headers'] ?? []);
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

        if ($format === OutputFormat::Json) {
            foreach (self::JSON_FLAGS as $key => $flag) {
                if (($options[$key] ?? false) === true) {
                    $command[] = $flag;
                }
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

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    private function appendOcrServerHeaders(array $command, mixed $headers): array
    {
        if (! is_array($headers)) {
            return $command;
        }

        foreach ($headers as $name => $value) {
            if (is_string($value)) {
                $command[] = '--ocr-server-header';
                $command[] = $name.': '.$value;
            }
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
