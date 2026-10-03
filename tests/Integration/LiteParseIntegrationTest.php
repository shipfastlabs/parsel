<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Enums\ImageMode;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Options\LiteParseOptions;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    requireBinary($this, 'lit', 'PARSEL_LITEPARSE_BINARY');
});

function demoPdf(): string
{
    return __DIR__.'/../../examples/docs/sample.pdf';
}

function integrationDirectory(): string
{
    $directory = sys_get_temp_dir().'/parsel-integration-'.bin2hex(random_bytes(6));
    mkdir($directory);

    return $directory;
}

function removeIntegrationDirectory(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($directory);
}

/** @return list<int> */
function pageNumbers(Document $document): array
{
    return array_map(static fn (Page $page): int => $page->number, $document->pages);
}

/** @return array{Process, string} */
function startFakeOcrServer(string $log): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0');

    if ($socket === false) {
        throw new RuntimeException('Could not reserve a port for the fake OCR server');
    }

    $address = (string) stream_socket_get_name($socket, false);
    fclose($socket);

    $process = new Process(
        [PHP_BINARY, '-S', $address, __DIR__.'/../Fixtures/ocr-server.php'],
        env: ['PARSEL_OCR_SERVER_LOG' => $log],
    );
    $process->setTimeout(120);
    $process->start();

    $started = $process->waitUntil(static fn (string $type, string $output): bool => str_contains($output, 'started'));

    if (! $started) {
        throw new RuntimeException('Fake OCR server did not start: '.$process->getErrorOutput());
    }

    return [$process, 'http://'.$address.'/ocr'];
}

it('extracts text from a real pdf with OCR disabled', function (): void {
    $text = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr())
        ->text();

    expect($text)->toContain('UNITED STATES')
        ->and($text)->toContain('SECURITIES AND EXCHANGE COMMISSION');
})->group('integration');

it('parses a real pdf into markdown', function (): void {
    $markdown = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr())
        ->markdown();

    expect($markdown)->toContain('UNITED STATES')
        ->and($markdown)->toContain('#');
})->group('integration');

it('parses a real pdf into a structured document with coordinates', function (): void {
    $document = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->pageRange(2, 3)->withoutOcr())
        ->parse();

    expect(pageNumbers($document))->toBe([2, 3])
        ->and($document->pages[0]->items)->not->toBeEmpty()
        ->and($document->pages[0]->items[0]->x)->toBeFloat();
})->group('integration');

it('selects individual pages and ranges together', function (): void {
    $document = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->pages(1, '3-4')->withoutOcr())
        ->parse();

    expect(pageNumbers($document))->toBe([1, 3, 4]);
})->group('integration');

it('limits the number of parsed pages', function (): void {
    $document = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->maxPages(2)->withoutOcr())
        ->parse();

    expect($document->pageCount())->toBe(2);
})->group('integration');

it('renders at a custom dpi while preserving small text', function (): void {
    $text = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr()->withDpi(72)->preserveSmallText())
        ->text();

    expect($text)->toContain('UNITED STATES');
})->group('integration');

it('passes a password to the real binary', function (): void {
    $text = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr()->withPassword('unused'))
        ->text();

    expect($text)->toContain('UNITED STATES');
})->group('integration');

it('streams pages of a real pdf lazily', function (): void {
    $pages = iterator_to_array(Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->pageRange(1, 2)->withoutOcr())
        ->lazyPages());

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->items)->not->toBeEmpty()
        ->and($pages[0]->items[0]->text)->toBeString();
})->group('integration');

it('writes embedded markdown images to a directory', function (): void {
    $directory = integrationDirectory();

    try {
        $markdown = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr()->withImages(ImageMode::Embed, $directory))
            ->markdown();

        expect($markdown)->toContain('UNITED STATES')
            ->and(glob($directory.'/*'))->not->toBeEmpty();
    } finally {
        removeIntegrationDirectory($directory);
    }
})->group('integration');

it('accepts the other markdown image modes', function (ImageMode $mode): void {
    $markdown = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withoutOcr()->withImages($mode))
        ->markdown();

    expect($markdown)->toContain('UNITED STATES');
})->with([ImageMode::Placeholder, ImageMode::Off])->group('integration');

it('renders and strips markdown links', function (): void {
    $withLinks = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(58)->withoutOcr())
        ->markdown();

    $withoutLinks = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(58)->withoutOcr()->withoutLinks())
        ->markdown();

    expect($withLinks)->toContain('](https://www.sec.gov/')
        ->and($withoutLinks)->not->toContain('](')
        ->and($withoutLinks)->toContain("Officer's Certificate");
})->group('integration');

it('keeps running headers and footers in markdown when asked', function (): void {
    $stripped = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->pageRange(4, 6)->withoutOcr())
        ->markdown();

    $kept = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->pageRange(4, 6)->withoutOcr()->keepHeadersAndFooters())
        ->markdown();

    expect($stripped)->not->toContain('2024 Form 10-K | 1')
        ->and($kept)->toContain('2024 Form 10-K | 1');
})->group('integration');

it('renders page screenshots at the requested dpi', function (): void {
    $directory = integrationDirectory();

    try {
        $files = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->pageRange(1, 2)->withDpi(36)->withPassword('unused'))
            ->screenshots($directory);

        expect($files)->toHaveCount(2);

        $size = getimagesize($files[0]);

        expect($size)->toBeArray()
            ->and($size[0] ?? 0)->toBeGreaterThan(280)->toBeLessThan(330);
    } finally {
        removeIntegrationDirectory($directory);
    }
})->group('integration');

it('returns only the screenshots a real run produced', function (): void {
    $directory = integrationDirectory();
    file_put_contents($directory.DIRECTORY_SEPARATOR.'.gitkeep', '');
    file_put_contents($directory.DIRECTORY_SEPARATOR.'page_99.png', 'stale');

    try {
        $first = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->pages(2, 10)->withDpi(20))
            ->screenshots($directory);

        $second = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(2)->withDpi(20))
            ->screenshots($directory);

        expect($first)->toBe([
            $directory.DIRECTORY_SEPARATOR.'page_2.png',
            $directory.DIRECTORY_SEPARATOR.'page_10.png',
        ])->and($second)->toBe([$directory.DIRECTORY_SEPARATOR.'page_2.png'])
            ->and(file_get_contents($directory.DIRECTORY_SEPARATOR.'page_99.png'))->toBe('stale')
            ->and(file_exists($directory.DIRECTORY_SEPARATOR.'.gitkeep'))->toBeTrue();
    } finally {
        array_map(unlink(...), glob($directory.DIRECTORY_SEPARATOR.'{,.}[!.]*', GLOB_BRACE) ?: []);
        rmdir($directory);
    }
})->group('integration');

it('sends the OCR language and worker flags to an OCR server', function (): void {
    $log = (string) tempnam(sys_get_temp_dir(), 'parsel-ocr');
    [$server, $url] = startFakeOcrServer($log);

    try {
        $text = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(1)->withOcr(language: 'fra', serverUrl: $url, workers: 1))
            ->text();

        expect($text)->toContain('PARSELOCRMARKER')
            ->and((string) file_get_contents($log))->toContain('"language":"fra"');
    } finally {
        $server->stop();
        unlink($log);
    }
})->group('integration');

it('runs built-in OCR with downloaded language models', function (): void {
    requireExtendedIntegration($this, 'needs network access to download Tesseract language models');

    $text = Parsel::file(demoPdf())
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withOcr(language: 'eng', workers: 1))
        ->text();

    expect($text)->toContain('UNITED STATES');
})->group('integration');

it('converts office documents through LibreOffice', function (): void {
    requireExtendedIntegration($this, 'needs a working LibreOffice installation');

    $text = Parsel::file(__DIR__.'/../../examples/docs/sample.docx')
        ->withProviderOptions(LiteParseOptions::make()->withoutOcr())
        ->text();

    expect($text)->not->toBeEmpty();
})->group('integration');

it('reads Tesseract language data from the configured tessdata path', function (): void {
    $directory = integrationDirectory();
    file_put_contents($directory.'/eng.traineddata', 'not a real model');

    try {
        Parsel::file(__DIR__.'/../../examples/docs/sample.png')
            ->withProviderOptions(LiteParseOptions::make()->withOcr(language: 'eng', tessdataPath: $directory, workers: 1))
            ->text();

        $this->fail('LiteParse accepted an invalid tessdata model');
    } catch (ParseFailedException $parseFailedException) {
        expect($parseFailedException->getMessage())
            ->not->toContain('--tessdata-path')
            ->toContain($directory.'/eng.traineddata');
    } finally {
        removeIntegrationDirectory($directory);
    }
})->group('integration');

it('runs built-in OCR with language data from a local tessdata path', function (): void {
    requireExtendedIntegration($this, 'needs network access to download a Tesseract language model');

    $directory = integrationDirectory();
    $model = @file_get_contents('https://cdn.jsdelivr.net/npm/@tesseract.js-data/eng@1.0.0/4.0.0_best_int/eng.traineddata.gz');
    $model = $model === false ? false : gzdecode($model);

    if ($model === false) {
        removeIntegrationDirectory($directory);
        $this->fail('Could not download the eng Tesseract language model');
    }

    file_put_contents($directory.'/eng.traineddata', $model);

    try {
        $text = Parsel::file(__DIR__.'/../../examples/docs/sample.png')
            ->withProviderOptions(LiteParseOptions::make()->withOcr(language: 'eng', tessdataPath: $directory, workers: 1))
            ->text();

        expect($text)->toContain('Shipfastlabs');
    } finally {
        removeIntegrationDirectory($directory);
    }
})->group('integration');

it('screenshots a real pdf without forwarding parse-only extra options', function (): void {
    $directory = integrationDirectory();

    try {
        $files = Parsel::file(demoPdf())
            ->withProviderOptions(LiteParseOptions::make()->page(1)->withDpi(36)->option('extract-blocks'))
            ->screenshots($directory);

        expect($files)->toHaveCount(1);
    } finally {
        removeIntegrationDirectory($directory);
    }
})->group('integration');
