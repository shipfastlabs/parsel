<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel;

use Generator;
use JsonException;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use Shipfastlabs\Parsel\Contracts\Filesystem;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Enums\OutputFormat;
use Shipfastlabs\Parsel\Exceptions\InvalidOutputException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\NativeFilesystem;
use Shipfastlabs\Parsel\Support\ProcessResult;
use Shipfastlabs\Parsel\Support\SymfonyProcessRunner;

final class PendingParse
{
    private ?string $pages = null;

    private ?int $maxPages = null;

    private bool $ocrDisabled = true;

    private ?string $ocrLanguage = null;

    private ?string $ocrServerUrl = null;

    private ?string $tessdataPath = null;

    private ?int $workers = null;

    private ?int $dpi = null;

    private bool $preserveSmallText = false;

    private ?string $password = null;

    /**
     * @var array<string, string|int|bool>
     */
    private array $extraOptions = [];

    public function __construct(
        private readonly Source $source,
        private readonly ProcessRunner $process = new SymfonyProcessRunner,
        private readonly BinaryResolver $resolver = new BinaryResolver,
        private readonly Filesystem $files = new NativeFilesystem,
        private ?string $binary = null,
        private ?float $timeout = 60.0,
    ) {}

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
        $this->maxPages = $max;

        return $this;
    }

    public function ocr(bool $enabled = true): self
    {
        $this->ocrDisabled = ! $enabled;

        return $this;
    }

    public function withOcr(
        ?string $language = null,
        ?string $tessdataPath = null,
        ?string $serverUrl = null,
        ?int $workers = null,
    ): self {
        $this->ocrDisabled = false;
        $this->ocrLanguage = $language ?? $this->ocrLanguage;
        $this->tessdataPath = $tessdataPath ?? $this->tessdataPath;
        $this->ocrServerUrl = $serverUrl ?? $this->ocrServerUrl;
        $this->workers = $workers ?? $this->workers;

        return $this;
    }

    public function withoutOcr(): self
    {
        return $this->ocr(false);
    }

    public function withDpi(int $dpi): self
    {
        $this->dpi = $dpi;

        return $this;
    }

    public function dpi(int $dpi): self
    {
        return $this->withDpi($dpi);
    }

    public function preserveSmallText(bool $preserve = true): self
    {
        $this->preserveSmallText = $preserve;

        return $this;
    }

    public function withPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function password(string $password): self
    {
        return $this->withPassword($password);
    }

    public function withBinary(string $path): self
    {
        $this->binary = $path;

        return $this;
    }

    public function binary(string $path): self
    {
        return $this->withBinary($path);
    }

    public function withTimeout(?float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function option(string $name, string|int|bool $value = true): self
    {
        $this->extraOptions[$name] = $value;

        return $this;
    }

    public function text(): string
    {
        return $this->stripPageHeaders($this->parseResult(OutputFormat::Text)->stdout);
    }

    public function parse(): Document
    {
        return Document::fromLiteParseJson($this->decodeJson());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Document::arrayFromLiteParseJson($this->decodeJson());
    }

    public function save(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $contents = $extension === 'json'
            ? trim($this->parseResult(OutputFormat::Json)->stdout)
            : $this->text();

        $this->files->put($path, $contents);

        return $path;
    }

    /**
     * @return list<string>
     */
    public function screenshots(string $directory): array
    {
        $this->runFor(fn (string $binary, string $file): array => $this->screenshotArgv($binary, $file, $directory));

        return $this->files->files($directory);
    }

    /**
     * @return Generator<int, Page>
     */
    public function lazyPages(): Generator
    {
        $output = $this->files->temporaryPath('json');

        try {
            $this->runToFile($output);

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

    private function appendPages(string $fragment): self
    {
        $this->pages = $this->pages === null ? $fragment : $this->pages.','.$fragment;

        return $this;
    }

    private function stripPageHeaders(string $text): string
    {
        $stripped = preg_replace('/^--- Page \d+ ---\R?/m', '', $text) ?? $text;

        return trim($stripped);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(): array
    {
        $stdout = trim($this->parseResult(OutputFormat::Json)->stdout);

        if ($stdout === '') {
            throw InvalidOutputException::emptyOutput();
        }

        try {
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidOutputException::malformedJson($jsonException->getMessage());
        }

        if (! is_array($decoded)) {
            throw InvalidOutputException::malformedJson('expected a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function parseResult(OutputFormat $format): ProcessResult
    {
        return $this->runFor(fn (string $binary, string $file): array => $this->parseArgv($binary, $file, $format));
    }

    /**
     * @param  callable(string, string): list<string>  $argv
     */
    private function runFor(callable $argv): ProcessResult
    {
        [$file, $temporary] = $this->resolveFile();

        try {
            $binary = $this->resolver->resolve($this->binary);
            $result = $this->process->run($argv($binary, $file), null, $this->timeout);
        } finally {
            if ($temporary !== null) {
                $this->files->delete($temporary);
            }
        }

        if (! $result->successful()) {
            throw ParseFailedException::fromResult($result);
        }

        return $result;
    }

    private function runToFile(string $output): void
    {
        [$file, $temporary] = $this->resolveFile();

        try {
            $binary = $this->resolver->resolve($this->binary);
            $result = $this->process->run($this->parseArgv($binary, $file, OutputFormat::Json, $output), null, $this->timeout);
        } finally {
            if ($temporary !== null) {
                $this->files->delete($temporary);
            }
        }

        if (! $result->successful()) {
            throw ParseFailedException::fromResult($result);
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function resolveFile(): array
    {
        if ($this->source->isBytes()) {
            $temporary = $this->files->temporaryPath($this->source->extension);
            $this->files->put($temporary, $this->source->contents());

            return [$temporary, $temporary];
        }

        return [$this->source->validatedPath($this->files), null];
    }

    /**
     * @return list<string>
     */
    private function parseArgv(string $binary, string $file, OutputFormat $format, ?string $output = null): array
    {
        $argv = [$binary, 'parse', $file, '--format', $format->value, '-q'];

        if ($output !== null) {
            $argv[] = '-o';
            $argv[] = $output;
        }

        $argv = $this->appendFlag($argv, 'target-pages', $this->pages);
        $argv = $this->appendFlag($argv, 'max-pages', $this->maxPages);
        $argv = $this->appendFlag($argv, 'password', $this->password);

        if ($this->ocrDisabled) {
            $argv[] = '--no-ocr';
        }

        $argv = $this->appendFlag($argv, 'ocr-language', $this->ocrLanguage);
        $argv = $this->appendFlag($argv, 'ocr-server-url', $this->ocrServerUrl);
        $argv = $this->appendFlag($argv, 'tessdata-path', $this->tessdataPath);
        $argv = $this->appendFlag($argv, 'num-workers', $this->workers);
        $argv = $this->appendFlag($argv, 'dpi', $this->dpi);

        if ($this->preserveSmallText) {
            $argv[] = '--preserve-small-text';
        }

        return $this->appendExtraOptions($argv);
    }

    /**
     * @return list<string>
     */
    private function screenshotArgv(string $binary, string $file, string $directory): array
    {
        $argv = [$binary, 'screenshot', $file, '-o', $directory, '-q'];

        $argv = $this->appendFlag($argv, 'target-pages', $this->pages);
        $argv = $this->appendFlag($argv, 'dpi', $this->dpi);
        $argv = $this->appendFlag($argv, 'password', $this->password);

        return $this->appendExtraOptions($argv);
    }

    /**
     * @param  list<string>  $argv
     * @return list<string>
     */
    private function appendFlag(array $argv, string $name, string|int|null $value): array
    {
        if ($value !== null) {
            $argv[] = '--'.$name;
            $argv[] = (string) $value;
        }

        return $argv;
    }

    /**
     * @param  list<string>  $argv
     * @return list<string>
     */
    private function appendExtraOptions(array $argv): array
    {
        foreach ($this->extraOptions as $name => $value) {
            if ($value === false) {
                continue;
            }

            $argv[] = '--'.$name;

            if ($value !== true) {
                $argv[] = (string) $value;
            }
        }

        return $argv;
    }
}
