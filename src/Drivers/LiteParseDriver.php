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
use Shipfastlabs\Parsel\Support\StagingDirectory;

final readonly class LiteParseDriver implements Driver, LazyPageDriver, ScreenshotDriver, StructuredDocumentDriver, TextDriver
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
        return Document::fromLiteParseJson($this->decodeJson($this->json($request)));
    }

    public function json(ParseRequest $request): string
    {
        return trim($this->parseResult($request, OutputFormat::Json));
    }

    public function screenshots(ParseRequest $request, string $directory): array
    {
        if (! $this->files->exists($directory) || is_file($directory)) {
            throw FilesystemException::directoryNotFound($directory);
        }

        $options = $request->options;
        $binary = $this->binary($options);
        $staging = new StagingDirectory($this->files);
        $output = $staging->create();

        try {
            $this->process->run(
                $request->source,
                function (string $file) use ($binary, $output, $options): array {
                    $command = CliArguments::command($binary, 'screenshot', $file, '-o', $output, '-q');
                    $command = CliArguments::flag($command, 'target-pages', CliArguments::scalar($options, 'pages'));
                    $command = CliArguments::flag($command, 'dpi', CliArguments::scalar($options, 'dpi'));
                    $command = CliArguments::flag($command, 'password', CliArguments::scalar($options, 'password'));

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

    public function pages(ParseRequest $request): Generator
    {
        $output = $this->files->temporaryPath('json');
        $options = $request->options;
        $binary = $this->binary($options);
        $config = $this->writeConfig($options);

        try {
            $this->process->run(
                $request->source,
                fn (string $file): array => $this->parseArgv($binary, $file, OutputFormat::Json, $options, $output, $config),
                $request->timeout,
                $this->name(),
            );

            if (! $this->files->exists($output)) {
                throw InvalidOutputException::emptyOutput($this->name());
            }

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

    /** @param array<string, mixed> $options */
    private function binary(array $options): string
    {
        return $this->resolver->resolve(CliArguments::string($options, 'binary') ?? $this->configuredBinary);
    }

    private function parseResult(ParseRequest $request, OutputFormat $format): string
    {
        $options = $request->options;
        $binary = $this->binary($options);
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
        $tessdata = CliArguments::string($options, 'tessdata_path');

        if (($options['ocr'] ?? false) !== true || $tessdata === null) {
            return null;
        }

        $settings = $this->userConfig(CliArguments::string($options, 'config'));

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
    private function parseArgv(string $binary, string $file, OutputFormat $format, array $options, ?string $output = null, ?string $config = null): array
    {
        $command = CliArguments::command($binary, 'parse', $file, '--format', $format->value, '-q');

        if ($output !== null) {
            $command[] = '-o';
            $command[] = $output;
        }

        $command = CliArguments::flag($command, 'target-pages', CliArguments::scalar($options, 'pages'));
        $command = CliArguments::flag($command, 'max-pages', CliArguments::scalar($options, 'max_pages'));
        $command = CliArguments::flag($command, 'password', CliArguments::scalar($options, 'password'));
        $command = CliArguments::flag($command, 'config', $config ?? CliArguments::string($options, 'config'));

        if (($options['continue_on_page_error'] ?? false) === true) {
            $command[] = '--continue-on-page-error';
        }

        if (($options['ocr'] ?? false) !== true) {
            $command[] = '--no-ocr';
        } else {
            $command = CliArguments::flag($command, 'ocr-language', CliArguments::scalar($options, 'ocr_language'));
            $command = CliArguments::flag($command, 'ocr-server-url', CliArguments::scalar($options, 'ocr_server_url'));
            $command = $this->appendOcrServerHeaders($command, $options['ocr_server_headers'] ?? []);
            $command = CliArguments::flag($command, 'num-workers', CliArguments::scalar($options, 'workers'));
        }

        $command = CliArguments::flag($command, 'dpi', CliArguments::scalar($options, 'dpi'));

        if (($options['preserve_small_text'] ?? false) === true) {
            $command[] = '--preserve-small-text';
        }

        if ($format === OutputFormat::Markdown) {
            $command = CliArguments::flag($command, 'image-mode', CliArguments::scalar($options, 'image_mode'));
            $command = CliArguments::flag($command, 'image-output-dir', CliArguments::scalar($options, 'image_directory'));

            if (($options['links'] ?? true) === false) {
                $command[] = '--no-links';
            }

            if (($options['keep_headers_and_footers'] ?? false) === true) {
                $command[] = '--keep-headers-footers';
            }
        }

        $command = $this->appendJsonFlags($command, $format, $options);

        return CliArguments::appendExtra($command, $options);
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function appendJsonFlags(array $command, OutputFormat $format, array $options): array
    {
        if ($format === OutputFormat::Json) {
            foreach (self::JSON_FLAGS as $key => $flag) {
                if (($options[$key] ?? false) === true) {
                    $command[] = $flag;
                }
            }
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
}
