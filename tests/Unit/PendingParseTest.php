<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Contracts\ProcessRunner;
use Shipfastlabs\Parsel\Data\Document;
use Shipfastlabs\Parsel\Data\Page;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\Exceptions\InvalidOutputException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\SourceNotFoundException;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Source;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;
use Tests\Doubles\FakeExecutableFinder;
use Tests\Doubles\FakeJsonOutputRunner;

it('extracts trimmed text', function (): void {
    Parsel::fake(['--format text' => '  hello world  ']);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('hello world');
});

it('strips lit page-header markers from text', function (): void {
    Parsel::fake(['--format text' => "--- Page 1 ---\nAlpha line\n--- Page 2 ---\nBeta line"]);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe("Alpha line\nBeta line");
});

it('returns an empty string for empty text output', function (): void {
    Parsel::fake(['--format text' => '']);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('');
});

it('parses into a structured document', function (): void {
    Parsel::fake(['--format json' => fixtureContents('liteparse-output.json')]);

    $document = Parsel::file(fixture('sample.pdf'))->pageRange(1, 5)->withOcr(language: 'eng')->withDpi(300)->parse();

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->pageCount())->toBe(2)
        ->and($document->pages[0]->items[0]->text)->toBe('UNITED STATES');
});

it('returns the document as an array', function (): void {
    Parsel::fake(['--format json' => fixtureContents('liteparse-output.json')]);

    expect(Parsel::file(fixture('sample.pdf'))->toArray())->toHaveKeys(['pages', 'text', 'metadata']);
});

it('saves liteparse json verbatim by extension', function (): void {
    Parsel::fake(['--format json' => fixtureContents('liteparse-output.json')]);
    $out = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_save_'.uniqid().'.json';

    expect(Parsel::file(fixture('sample.pdf'))->save($out))->toBe($out)
        ->and(file_get_contents($out))->toContain('"text_items"');

    unlink($out);
});

it('saves text output by extension', function (): void {
    Parsel::fake(['--format text' => 'plain text body']);
    $out = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_save_'.uniqid().'.txt';

    Parsel::file(fixture('sample.pdf'))->save($out);

    expect(file_get_contents($out))->toBe('plain text body');

    unlink($out);
});

it('parses raw bytes through a temporary file', function (): void {
    Parsel::fake(['--format text' => 'from bytes']);

    expect(Parsel::bytes('rawdata', 'pdf')->text())->toBe('from bytes');
});

it('streams pages lazily from a file source', function (): void {
    $runner = new FakeJsonOutputRunner(fixtureContents('liteparse-output.json'));
    $parse = new PendingParse(Source::fromPath(fixture('sample.pdf')), $runner, binary: 'lit');

    $pages = iterator_to_array($parse->lazyPages());

    expect($pages)->toHaveCount(2)
        ->and($pages[0])->toBeInstanceOf(Page::class)
        ->and($pages[0]->items[0]->text)->toBe('UNITED STATES');
});

it('streams pages lazily from a bytes source', function (): void {
    $runner = new FakeJsonOutputRunner(fixtureContents('liteparse-output.json'));
    $parse = new PendingParse(Source::fromBytes('rawdata', 'pdf'), $runner, binary: 'lit');

    expect(iterator_to_array($parse->lazyPages()))->toHaveCount(2);
});

it('skips non-array page entries while streaming', function (): void {
    $runner = new FakeJsonOutputRunner('{"pages":["bad",{"page":1,"text":"a","text_items":[]}]}');
    $parse = new PendingParse(Source::fromPath(fixture('sample.pdf')), $runner, binary: 'lit');

    expect(iterator_to_array($parse->lazyPages()))->toHaveCount(1);
});

it('throws when the streaming process fails', function (): void {
    $runner = new FakeJsonOutputRunner('', 3);
    $parse = new PendingParse(Source::fromPath(fixture('sample.pdf')), $runner, binary: 'lit');

    iterator_to_array($parse->lazyPages());
})->throws(ParseFailedException::class);

it('builds the screenshot argv including extra options', function (): void {
    $fake = new FakeProcessRunner(['screenshot' => '']);
    $parse = new PendingParse(Source::fromPath(fixture('sample.pdf')), $fake, binary: 'lit');
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_shots_'.uniqid();
    mkdir($directory);

    $parse->page(1)->withDpi(150)->withPassword('pw')->option('foo')->screenshots($directory);

    expect($fake->recordedCommands()[0])
        ->toContain('screenshot', '-o', $directory, '--target-pages', '1', '--dpi', '150', '--password', 'pw', '--foo');

    rmdir($directory);
});

it('returns all files in the screenshot directory', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_shots_'.uniqid();
    mkdir($directory);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'old.png', 'stale');

    Parsel::swap(new readonly class($directory) implements ProcessRunner
    {
        public function __construct(private string $directory) {}

        public function run(array $command, ?string $input = null, ?float $timeout = 60.0): ProcessResult
        {
            file_put_contents($this->directory.DIRECTORY_SEPARATOR.'page_1.png', 'png');

            return new ProcessResult(0, '', '', $command);
        }
    });

    $files = Parsel::file(fixture('sample.pdf'))->screenshots($directory);

    expect($files)->toBe([
        $directory.DIRECTORY_SEPARATOR.'old.png',
        $directory.DIRECTORY_SEPARATOR.'page_1.png',
    ]);

    unlink($directory.DIRECTORY_SEPARATOR.'old.png');
    unlink($directory.DIRECTORY_SEPARATOR.'page_1.png');
    rmdir($directory);
});

it('builds the base parse argv with quiet and format', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->text();

    expect($fake->recordedCommands()[0])->toBe(['lit', 'parse', fixture('sample.pdf'), '--format', 'text', '-q', '--no-ocr']);
});

it('maps simple options to flags', function (string $method, array $args, array $expected): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->{$method}(...$args)->text();

    expect($fake->recordedCommands()[0])->toContain(...$expected);
})->with([
    'maxPages' => ['maxPages', [50], ['--max-pages', '50']],
    'password' => ['password', ['secret'], ['--password', 'secret']],
    'dpi' => ['dpi', [300], ['--dpi', '300']],
]);

it('adds --no-ocr by default', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->text();

    expect($fake->recordedCommands()[0])->toContain('--no-ocr');
});

it('adds --no-ocr when withoutOcr is called', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->withoutOcr()->text();

    expect($fake->recordedCommands()[0])->toContain('--no-ocr');
});

it('does not add --no-ocr when withOcr is called', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->withOcr()->text();

    expect($fake->recordedCommands()[0])->not->toContain('--no-ocr');
});

it('does not add --no-ocr when ocr(true) is called', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->ocr(true)->text();

    expect($fake->recordedCommands()[0])->not->toContain('--no-ocr');
});

it('maps withOcr parameters to flags and enables ocr', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->withOcr(
        language: 'fra',
        tessdataPath: '/usr/share/tessdata',
        serverUrl: 'http://localhost:8828/ocr',
        workers: 8,
    )->text();

    $command = $fake->recordedCommands()[0];

    expect($command)
        ->toContain('--ocr-language', 'fra', '--tessdata-path', '/usr/share/tessdata', '--ocr-server-url', 'http://localhost:8828/ocr', '--num-workers', '8')
        ->and($command)->not->toContain('--no-ocr');
});

it('adds --preserve-small-text', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->preserveSmallText()->text();

    expect($fake->recordedCommands()[0])->toContain('--preserve-small-text');
});

it('normalizes page selection', function (Closure $build, string $expected): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    $build(fakeParse($fake))->text();

    $command = $fake->recordedCommands()[0];
    $index = array_search('--target-pages', $command, true);

    expect($command[$index + 1])->toBe($expected);
})->with([
    'single page' => [fn (PendingParse $parse): PendingParse => $parse->page(7), '7'],
    'page list' => [fn (PendingParse $parse): PendingParse => $parse->pages(1, 3, 5), '1,3,5'],
    'range strings' => [fn (PendingParse $parse): PendingParse => $parse->pages('2-4', 9), '2-4,9'],
    'inclusive range' => [fn (PendingParse $parse): PendingParse => $parse->pageRange(1, 5), '1-5'],
    'additive' => [fn (PendingParse $parse): PendingParse => $parse->pageRange(1, 5)->page(10), '1-5,10'],
]);

it('appends a boolean escape-hatch option as a bare flag', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->option('experimental')->text();

    expect($fake->recordedCommands()[0])->toContain('--experimental');
});

it('appends a scalar escape-hatch option with its value', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->option('threads', 8)->text();

    expect($fake->recordedCommands()[0])->toContain('--threads', '8');
});

it('omits an escape-hatch option set to false', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->option('debug', false)->text();

    expect($fake->recordedCommands()[0])->not->toContain('--debug');
});

it('uses a per-call binary override', function (): void {
    $fake = new FakeProcessRunner(['--format text' => '']);
    $parse = new PendingParse(Source::fromPath(fixture('sample.pdf')), $fake);

    $parse->withBinary('/custom/lit')->text();

    expect($fake->recordedCommands()[0][0])->toBe('/custom/lit');
});

it('accepts a timeout override', function (): void {
    $fake = new FakeProcessRunner(['--format text' => 'ok']);

    expect(fakeParse($fake)->withTimeout(1.5)->text())->toBe('ok');
});

it('throws and carries details when the process exits non-zero', function (): void {
    $fake = new FakeProcessRunner(['parse' => new ProcessResult(5, '', 'boom', ['lit', 'parse', 'f'])]);
    $caught = null;

    try {
        fakeParse($fake)->text();
    } catch (ParseFailedException $parseFailedException) {
        $caught = $parseFailedException;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->exitCode)->toBe(5)
        ->and($caught->stderr)->toBe('boom')
        ->and($caught->command)->toBe(['lit', 'parse', 'f']);
});

it('throws on empty json output', function (): void {
    fakeParse(new FakeProcessRunner(['--format json' => '']))->parse();
})->throws(InvalidOutputException::class, 'empty output');

it('throws on malformed json', function (): void {
    fakeParse(new FakeProcessRunner(['--format json' => '{not valid']))->parse();
})->throws(InvalidOutputException::class, 'malformed JSON');

it('throws when json is not an object', function (): void {
    fakeParse(new FakeProcessRunner(['--format json' => '42']))->parse();
})->throws(InvalidOutputException::class, 'expected a JSON object');

it('throws before running when the source file is missing', function (): void {
    $fake = new FakeProcessRunner(['parse' => 'unused']);
    $parse = new PendingParse(Source::fromPath('/no/such/file.pdf'), $fake, binary: 'lit');
    $caught = null;

    try {
        $parse->text();
    } catch (SourceNotFoundException $sourceNotFoundException) {
        $caught = $sourceNotFoundException;
    }

    expect($caught)->not->toBeNull()
        ->and($fake->ranCount())->toBe(0);
});

it('throws when the binary cannot be resolved', function (): void {
    $parse = new PendingParse(
        Source::fromPath(fixture('sample.pdf')),
        new FakeProcessRunner,
        new BinaryResolver(new FakeExecutableFinder),
    );

    $parse->text();
})->throws(BinaryNotFoundException::class);

it('supports backward-compat aliases for renamed methods', function (string $method, array $args, array $expected): void {
    $fake = new FakeProcessRunner(['--format text' => '']);

    fakeParse($fake)->{$method}(...$args)->text();

    expect($fake->recordedCommands()[0])->toContain(...$expected);
})->with([
    'dpi alias' => ['dpi', [300], ['--dpi', '300']],
    'password alias' => ['password', ['secret'], ['--password', 'secret']],
    'binary alias' => ['binary', ['/custom/lit'], ['/custom/lit']],
]);
