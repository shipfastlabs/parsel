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
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;
use Tests\Doubles\FakeJsonOutputRunner;

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

it('builds screenshot commands and returns destination files', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_shots_'.uniqid();
    mkdir($directory);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'page.png', 'png');
    $fake = new FakeProcessRunner(['screenshot' => '']);

    $files = fakeParse($fake)
        ->withProviderOptions(LiteParseOptions::make()->page(1)->withDpi(150)->withPassword('pw')->option('foo'))
        ->screenshots($directory);

    expect($files)->toBe([$directory.DIRECTORY_SEPARATOR.'page.png'])
        ->and($fake->recordedCommands()[0])->toContain('screenshot', '--target-pages', '1', '--dpi', '150', '--password', 'pw', '--foo');

    unlink($directory.DIRECTORY_SEPARATOR.'page.png');
    rmdir($directory);
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
