<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Enums\ImageMode;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Exceptions\InvalidOutputException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\ParseTimedOutException;
use Shipfastlabs\Parsel\Exceptions\SourceNotFoundException;
use Shipfastlabs\Parsel\Options\LiteParseOptions;
use Shipfastlabs\Parsel\ParselManager;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;
use Shipfastlabs\Parsel\Support\SymfonyProcessRunner;
use Tests\Doubles\ConfigCapturingRunner;
use Tests\Doubles\FakeJsonOutputRunner;
use Tests\Doubles\FakeScreenshotRunner;

it('returns text markdown structured documents and arrays', function (): void {
    Parsel::fake([
        '--format text' => "--- Page 1 ---\n  hello world  ",
        '--format markdown' => "\n# Heading\n",
        '--format json' => fixtureContents('liteparse-output.json'),
    ]);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('hello world')
        ->and(Parsel::file(fixture('sample.pdf'))->markdown())->toBe('# Heading')
        ->and(Parsel::file(fixture('sample.pdf'))->parse())->toBeInstanceOf(Document::class)
        ->and(Parsel::file(fixture('sample.pdf'))->toArray())->toHaveKeys(['pages', 'text', 'metadata']);
});

it('maps all typed options to the appropriate CLI flags', function (): void {
    $fake = new FakeProcessRunner(['--format markdown' => 'ok']);
    $options = LiteParseOptions::make()
        ->pages(1, '3-4')->pageRange(7, 8)->page(10)
        ->maxPages(50)
        ->withOcr('fra', '/tess', 'http://ocr', 8)
        ->withDpi(300)
        ->preserveSmallText()
        ->withPassword('secret')
        ->withImages(ImageMode::Embed, '/images')
        ->withoutLinks()
        ->keepHeadersAndFooters()
        ->option('experimental')
        ->option('threads', 4)
        ->option('ignored', false);

    fakeParse($fake)->withProviderOptions($options)->markdown();

    expect($fake->recordedCommands()[0])->toContain(
        '--target-pages', '1,3-4,7-8,10', '--max-pages', '50',
        '--ocr-language', 'fra', '--config', '--ocr-server-url', 'http://ocr', '--num-workers', '8',
        '--dpi', '300', '--preserve-small-text', '--password', 'secret',
        '--image-mode', 'embed', '--image-output-dir', '/images', '--no-links', '--keep-headers-footers',
        '--experimental', '--threads', '4',
    )->not->toContain('--no-ocr', '--ignored', '--tessdata-path');
});

it('passes the tessdata path through a temporary LiteParse config file', function (): void {
    $runner = new ConfigCapturingRunner;

    $text = new ParselManager(process: $runner, binaries: ['liteparse' => 'lit'])
        ->file(fixture('sample.pdf'))
        ->withProviderOptions(LiteParseOptions::make()->withOcr(tessdataPath: '/usr/share/tessdata'))
        ->text();

    expect($text)->toBe('ok')
        ->and($runner->configContents)->toBe('{"tessdataPath":"/usr/share/tessdata"}')
        ->and($runner->configPath)->not->toBeNull()
        ->and(file_exists((string) $runner->configPath))->toBeFalse();
});

it('merges a user config file with the tessdata path because lit reads only one config', function (): void {
    $config = (string) tempnam(sys_get_temp_dir(), 'parsel-user-config');
    file_put_contents($config, '{"maxPages":1}');
    $runner = new ConfigCapturingRunner;

    try {
        new ParselManager(process: $runner, binaries: ['liteparse' => 'lit'])
            ->file(fixture('sample.pdf'))
            ->withProviderOptions(LiteParseOptions::make()->withConfig($config)->withOcr(tessdataPath: '/tess'))
            ->text();
    } finally {
        unlink($config);
    }

    expect($runner->configContents)->toBe('{"maxPages":1,"tessdataPath":"/tess"}')
        ->and($runner->configPath)->not->toBe($config)
        ->and(file_exists((string) $runner->configPath))->toBeFalse();
});

it('passes an unreadable user config through unchanged so lit can report it', function (): void {
    $fake = new FakeProcessRunner(['parse' => 'ok']);

    fakeParse($fake)
        ->withProviderOptions(LiteParseOptions::make()->withConfig('/missing/config.json')->withOcr(tessdataPath: '/tess'))
        ->text();

    expect($fake->recordedCommands()[0])->toContain('--config', '/missing/config.json');
});

it('passes the tessdata config file when streaming pages', function (): void {
    $runner = new ConfigCapturingRunner;

    $pages = iterator_to_array(new ParselManager(process: $runner, binaries: ['liteparse' => 'lit'])
        ->file(fixture('sample.pdf'))
        ->withProviderOptions(['ocr' => true, 'tessdata_path' => '/tess'])
        ->lazyPages());

    expect($pages)->toBe([])
        ->and($runner->configContents)->toBe('{"tessdataPath":"/tess"}')
        ->and(file_exists((string) $runner->configPath))->toBeFalse();
});

it('ignores the tessdata path when OCR is disabled', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)->withProviderOptions(LiteParseOptions::make()->withOcr(tessdataPath: '/tess')->withoutOcr())->text();

    expect($fake->recordedCommands()[0])->toContain('--no-ocr')->not->toContain('--config', '--tessdata-path');
});

it('omits disabled and format-inapplicable options', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);
    $options = LiteParseOptions::make()
        ->withOcr(language: 'eng')
        ->withoutOcr()
        ->withoutImages()
        ->links()
        ->keepHeadersAndFooters(false)
        ->preserveSmallText(false);

    fakeParse($fake)->withProviderOptions($options)->text();
    $command = $fake->recordedCommands()[0];

    expect($command)->toContain('--no-ocr')
        ->not->toContain('--ocr-language', '--image-mode', '--no-links', '--keep-headers-footers', '--preserve-small-text');
});

it('maps OCR server headers, page error recovery and config files to CLI flags', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);
    $options = LiteParseOptions::make()
        ->withOcr(serverUrl: 'http://ocr', headers: ['Authorization' => 'Bearer token'])
        ->withOcrServerHeader('X-Tenant', 'acme')
        ->continueOnPageError()
        ->withConfig('/etc/liteparse.json');

    fakeParse($fake)->withProviderOptions($options)->text();

    expect($fake->recordedCommands()[0])->toBe([
        'lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q',
        '--config', '/etc/liteparse.json', '--continue-on-page-error',
        '--ocr-server-url', 'http://ocr',
        '--ocr-server-header', 'Authorization: Bearer token',
        '--ocr-server-header', 'X-Tenant: acme',
    ]);
});

it('omits OCR server headers when OCR is disabled and page error recovery when turned off', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);
    $options = LiteParseOptions::make()
        ->withOcrServerHeader('Authorization', 'Bearer token')
        ->continueOnPageError(false);

    fakeParse($fake)->withProviderOptions($options)->text();

    expect($fake->recordedCommands()[0])->toBe(['lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q', '--no-ocr']);
});

it('accepts the new options as strict array keys and skips malformed headers', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)->withProviderOptions([
        'ocr' => true,
        'ocr_server_headers' => ['X-Key' => 'abc', 'X-Bad' => 1, 42 => 'numeric'],
        'continue_on_page_error' => true,
        'config' => '/cfg.json',
    ])->text();

    expect($fake->recordedCommands()[0])->toBe([
        'lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q',
        '--config', '/cfg.json', '--continue-on-page-error',
        '--ocr-server-header', 'X-Key: abc', '--ocr-server-header', '42: numeric',
    ]);
});

it('ignores a non-array OCR server header option', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)->withProviderOptions(['ocr' => true, 'ocr_server_headers' => 'X-Key: abc', 'config' => 42])->text();

    expect($fake->recordedCommands()[0])->toBe(['lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q']);
});

it('ignores a malformed raw option bucket safely', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)->withProviderOptions(['extra' => 'invalid'])->text();

    expect($fake->recordedCommands()[0])->toBe(['lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q', '--no-ocr']);
});

it('returns current lit text output with blank-line page separators intact', function (): void {
    Parsel::fake(['--format text' => fixtureContents('liteparse-output.txt')]);

    $text = Parsel::file(fixture('sample.pdf'))->text();

    expect($text)->toStartWith('UNITED STATES')
        ->and($text)->toContain("FORM 10-K\n\n    Indicate by check mark")
        ->and($text)->toEndWith('for the past 90 days.')
        ->and($text)->not->toContain('--- Page');
});

it('strips page markers from legacy lit text output', function (): void {
    Parsel::fake(['--format text' => fixtureContents('liteparse-output-legacy.txt')]);
    $legacy = Parsel::file(fixture('sample.pdf'))->text();

    Parsel::fake(['--format text' => fixtureContents('liteparse-output.txt')]);

    expect($legacy)->toBe(Parsel::file(fixture('sample.pdf'))->text());
});

it('preserves marker-like lines when the output does not use legacy page markers', function (): void {
    Parsel::fake(['--format text' => "Intro\n--- Page 2 ---\nBody"]);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe("Intro\n--- Page 2 ---\nBody");
});

it('normalizes form feed page breaks into blank lines', function (): void {
    Parsel::fake(['--format text' => "first page\n\fsecond page\fthird page\n"]);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe("first page\n\nsecond page\n\nthird page");
});

it('safely ignores malformed raw array entries', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)->withProviderOptions(['extra' => [true, 'ratio' => 1.5]])->text();

    expect($fake->recordedCommands()[0])->not->toContain('--0', '--ratio');
});

it('supports per-request binary and timeout settings', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    expect(fakeParse($fake)->withProviderOptions(LiteParseOptions::make()->withBinary('/custom/lit'))->withTimeout(1.5)->text())
        ->toBe('ok')
        ->and($fake->recordedCommands()[0][0])->toBe('/custom/lit');
});

it('uses temporary files for byte sources', function (): void {
    Parsel::fake(['--format text' => 'from bytes']);

    expect(Parsel::bytes('rawdata', 'pdf')->text())->toBe('from bytes');
});

it('streams valid pages and skips invalid JSON entries', function (): void {
    $runner = new FakeJsonOutputRunner('{"pages":["bad",{"page":1,"text":"a","textItems":[]}]}');
    $pages = iterator_to_array(new ParselManager(process: $runner, binaries: ['liteparse' => 'lit'])->file(fixture('sample.pdf'))->lazyPages());

    expect($pages)->toHaveCount(1)->and($pages[0])->toBeInstanceOf(Page::class);
});

it('propagates a failed lazy parsing process', function (): void {
    iterator_to_array(new ParselManager(process: new FakeJsonOutputRunner('', 3), binaries: ['liteparse' => 'lit'])->file(fixture('sample.pdf'))->lazyPages());
})->throws(ParseFailedException::class, 'liteparse exited');

it('builds screenshot commands that render into a private staging directory', function (): void {
    $directory = screenshotDirectory();
    $fake = new FakeProcessRunner(['screenshot' => '']);

    $files = fakeParse($fake)
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withDpi(150)->withPassword('pw')->screenshotOption('foo')->screenshotOption('scale', 2))
        ->screenshots($directory);

    $command = $fake->recordedCommands()[0];
    $output = $command[(int) array_search('-o', $command, true) + 1];

    expect($files)->toBe([])
        ->and($command)->toContain('screenshot', '--target-pages', '1', '--dpi', '150', '--password', 'pw', '--foo', '--scale', '2')
        ->and($output)->not->toBe($directory)
        ->and(file_exists($output))->toBeFalse();

    removeScreenshotDirectory($directory);
});

it('passes parse-only extra options to parse but not to screenshot', function (): void {
    $directory = screenshotDirectory();
    $fake = new FakeProcessRunner(['screenshot' => '', '--format text' => 'ok']);
    $options = LiteParseOptions::make()->option('extract-blocks')->screenshotOption('shot-only');

    fakeParse($fake)->withProviderOptions($options)->screenshots($directory);
    fakeParse($fake)->withProviderOptions($options)->text();

    [$screenshot, $parse] = $fake->recordedCommands();

    expect($screenshot)->toContain('screenshot', '--shot-only')->not->toContain('--extract-blocks')
        ->and($parse)->toContain('--extract-blocks')->not->toContain('--shot-only');

    removeScreenshotDirectory($directory);
});

it('moves only the screenshots produced by the run into the destination in page order', function (): void {
    $directory = screenshotDirectory(['.gitkeep', 'page_2.png', 'page_7.png']);
    $runner = new FakeScreenshotRunner(['page_10.png', 'page_2.png', 'page_1.png', 'notes.txt', 'page_01.png']);

    $files = screenshotParse($runner)->screenshots($directory.DIRECTORY_SEPARATOR);

    expect($files)->toBe([
        $directory.DIRECTORY_SEPARATOR.'page_1.png',
        $directory.DIRECTORY_SEPARATOR.'page_2.png',
        $directory.DIRECTORY_SEPARATOR.'page_10.png',
    ])
        ->and(file_get_contents($directory.DIRECTORY_SEPARATOR.'page_2.png'))->toBe('png')
        ->and(file_get_contents($directory.DIRECTORY_SEPARATOR.'page_7.png'))->toBe('stale')
        ->and(file_exists($directory.DIRECTORY_SEPARATOR.'.gitkeep'))->toBeTrue()
        ->and(file_exists($directory.DIRECTORY_SEPARATOR.'notes.txt'))->toBeFalse()
        ->and(file_exists((string) $runner->outputDirectory))->toBeFalse();

    removeScreenshotDirectory($directory);
});

it('removes the staging directory when the screenshot process fails', function (): void {
    $directory = screenshotDirectory();
    $runner = new FakeScreenshotRunner(['page_1.png'], 3);

    expect(fn (): array => screenshotParse($runner)->screenshots($directory))->toThrow(ParseFailedException::class)
        ->and(file_exists((string) $runner->outputDirectory))->toBeFalse()
        ->and(scandir($directory))->toBe(['.', '..']);

    removeScreenshotDirectory($directory);
});

it('removes the staging directory when a screenshot cannot be moved', function (): void {
    $directory = screenshotDirectory();
    mkdir($directory.DIRECTORY_SEPARATOR.'page_1.png');
    $runner = new FakeScreenshotRunner(['page_1.png']);

    expect(fn (): array => screenshotParse($runner)->screenshots($directory))->toThrow(FilesystemException::class)
        ->and(file_exists((string) $runner->outputDirectory))->toBeFalse();

    rmdir($directory.DIRECTORY_SEPARATOR.'page_1.png');
    removeScreenshotDirectory($directory);
});

it('requires the screenshot destination to exist', function (): void {
    Parsel::fake();
    Parsel::file(fixture('sample.pdf'))->screenshots('/missing/parsel/directory');
})->throws(FilesystemException::class);

it('rejects invalid structured output', function (string $json): void {
    fakeParse(new FakeProcessRunner(['--format json' => $json]))->parse();
})->throws(InvalidOutputException::class)->with([
    'empty output' => [''],
    'malformed JSON' => ['{bad'],
    'non-object JSON' => ['42'],
]);

it('preserves process failure details', function (): void {
    $failed = new FakeProcessRunner(['parse' => new ProcessResult(5, '', 'boom', ['lit', 'parse'])]);

    expect(fn (): string => fakeParse($failed)->text())->toThrow(ParseFailedException::class, 'boom');
});

it('does not expose the document password on a process failure', function (): void {
    $pending = new ParselManager(process: new FakeJsonOutputRunner('', 1), binaries: ['liteparse' => 'lit'])
        ->file(fixture('sample.pdf'))
        ->withProviderOptions(LiteParseOptions::make()->withPassword('hunter2'));

    expect(fn (): string => $pending->text())->toThrow(function (ParseFailedException $exception): void {
        expect($exception->command)->toContain('--password')->toContain('********')->not->toContain('hunter2')
            ->and($exception->getMessage())->not->toContain('hunter2')
            ->and(print_r(array_slice($exception->getTrace(), 0, 2), true))->not->toContain('hunter2');
    });
});

it('translates a timed out process into a parsel exception', function (): void {
    $timedOut = new FakeProcessRunner(['parse' => new ProcessResult(143, '', '', ['lit', 'parse'], 5.0)]);

    expect(fn (): string => fakeParse($timedOut)->text())
        ->toThrow(ParseTimedOutException::class, 'liteparse timed out after 5 seconds.');
});

it('stops a slow binary at the configured timeout and removes temporary files', function (): void {
    $binary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_slow_'.uniqid().'.sh';
    file_put_contents($binary, "#!/bin/sh\nsleep 5\n");
    chmod($binary, 0755);

    $before = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_*.pdf') ?: [];

    try {
        new ParselManager(process: new SymfonyProcessRunner, timeout: 0.2, binaries: ['liteparse' => $binary])
            ->bytes('%PDF-1.4', 'pdf')
            ->withProviderOptions([])
            ->text();

        $this->fail('Expected the parse to time out.');
    } catch (ParseTimedOutException $parseTimedOutException) {
        expect($parseTimedOutException->timeout)->toBe(0.2)
            ->and($parseTimedOutException->driver)->toBe('liteparse')
            ->and($parseTimedOutException->command[0])->toBe($binary);
    } finally {
        unlink($binary);
    }

    expect(glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_*.pdf') ?: [])->toBe($before);
})->skipOnWindows();
it('does not apply anydoc exit code mapping to liteparse failures', function (int $exitCode): void {
    $failed = new FakeProcessRunner(['parse' => new ProcessResult($exitCode, '', 'boom', ['lit', 'parse'])]);

    try {
        fakeParse($failed)->text();
        $this->fail('Expected parsing to fail.');
    } catch (ParseFailedException $parseFailedException) {
        expect($parseFailedException::class)->toBe(ParseFailedException::class);
    }
})->with([2, 3]);

it('validates sources before starting a process', function (): void {
    $unused = new FakeProcessRunner;

    expect(fn (): string => new ParselManager(process: $unused, binaries: ['liteparse' => 'lit'])->file('/no/such/file.pdf')->text())
        ->toThrow(SourceNotFoundException::class)
        ->and($unused->ranCount())->toBe(0);
});

/** @param list<string> $files */
function screenshotDirectory(array $files = []): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_shots_'.uniqid();
    mkdir($directory);

    foreach ($files as $name) {
        file_put_contents($directory.DIRECTORY_SEPARATOR.$name, 'stale');
    }

    return $directory;
}

function removeScreenshotDirectory(string $directory): void
{
    foreach (scandir($directory) ?: [] as $name) {
        if (! in_array($name, ['.', '..'], true)) {
            unlink($directory.DIRECTORY_SEPARATOR.$name);
        }
    }

    rmdir($directory);
}

function screenshotParse(FakeScreenshotRunner $runner): PendingParse
{
    return new ParselManager(process: $runner, binaries: ['liteparse' => 'lit'])->file(fixture('sample.pdf'));
}

it('sends JSON enrichment flags for structured output', function (): void {
    $fake = new FakeProcessRunner(['--format json' => fixtureContents('liteparse-output.json')]);

    fakeParse($fake)->withProviderOptions(LiteParseOptions::make()->extractAll())->parse();

    expect($fake->recordedCommands()[0])->toContain(
        '--extract-blocks', '--extract-annotations', '--extract-form-fields', '--extract-structure-tree',
        '--extract-content-bounds', '--extract-vector-graphics', '--extract-text-metadata', '--extract-images',
        '--extract-xfa-packets', '--complexity',
    );
});

it('omits disabled JSON enrichment flags', function (): void {
    $fake = new FakeProcessRunner(['--format json' => fixtureContents('liteparse-output.json')]);

    fakeParse($fake)->withProviderOptions([
        'extract_blocks' => true,
        'extract_images' => false,
        'complexity' => 'yes',
    ])->parse();

    expect($fake->recordedCommands()[0])->toContain('--extract-blocks')
        ->not->toContain('--extract-images', '--complexity');
});

it('does not send JSON enrichment flags for text or markdown output', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok', '--format markdown' => 'ok']);
    $options = LiteParseOptions::make()->extractAll();

    fakeParse($fake)->withProviderOptions($options)->text();
    fakeParse($fake)->withProviderOptions($options)->markdown();

    foreach ($fake->recordedCommands() as $command) {
        expect(array_filter($command, fn (string $part): bool => str_starts_with($part, '--extract-') || $part === '--complexity'))->toBe([]);
    }
});
