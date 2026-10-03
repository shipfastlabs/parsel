<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Enums\ImageMode;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Exceptions\InvalidOutputException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\SourceNotFoundException;
use Shipfastlabs\Parsel\Options\LiteParseOptions;
use Shipfastlabs\Parsel\ParselManager;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;
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
        '--ocr-language', 'fra', '--tessdata-path', '/tess', '--ocr-server-url', 'http://ocr', '--num-workers', '8',
        '--dpi', '300', '--preserve-small-text', '--password', 'secret',
        '--image-mode', 'embed', '--image-output-dir', '/images', '--no-links', '--keep-headers-footers',
        '--experimental', '--threads', '4',
    )->not->toContain('--no-ocr', '--ignored');
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

it('ignores a malformed raw option bucket safely', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    fakeParse($fake)->withProviderOptions(['extra' => 'invalid'])->text();

    expect($fake->recordedCommands()[0])->toBe(['lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q', '--no-ocr']);
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
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withDpi(150)->withPassword('pw')->option('foo'))
        ->screenshots($directory);

    $command = $fake->recordedCommands()[0];
    $output = $command[(int) array_search('-o', $command, true) + 1];

    expect($files)->toBe([])
        ->and($command)->toContain('screenshot', '--target-pages', '1', '--dpi', '150', '--password', 'pw', '--foo')
        ->and($output)->not->toBe($directory)
        ->and(file_exists($output))->toBeFalse();

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
