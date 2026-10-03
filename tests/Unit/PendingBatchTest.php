<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\FilesystemException;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\UnsupportedCapabilityException;
use Shipfastlabs\Parsel\Options\AnyDocOptions;
use Shipfastlabs\Parsel\Options\LiteParseOptions;
use Shipfastlabs\Parsel\ParselManager;
use Shipfastlabs\Parsel\PendingBatch;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;
use Shipfastlabs\Parsel\Support\ProcessResult;

/**
 * @param  array<string, string>  $files  Relative path => contents.
 */
function batchDirectory(array $files = []): string
{
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parsel_batch_'.uniqid();
    mkdir($root);

    foreach ($files as $relative => $contents) {
        $path = $root.DIRECTORY_SEPARATOR.$relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), recursive: true);
        }

        file_put_contents($path, $contents);
    }

    return $root;
}

function removeBatchDirectory(string $directory): void
{
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        /** @var SplFileInfo $item */
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
}

function fakeBatch(FakeProcessRunner $runner, string $directory): PendingBatch
{
    return new ParselManager(process: $runner, binaries: ['liteparse' => 'lit'])->directory($directory);
}

it('builds a batch-parse command and returns the written files', function (): void {
    $input = batchDirectory(['a.pdf' => '', 'notes.PDF' => '', 'image.png' => '', 'sub/b.pdf' => '', 'sub/deeper/c.pdf' => '']);
    $output = batchDirectory(['a.md' => '', 'notes.md' => '', 'sub/b.md' => '', 'sub/deeper/c.md' => '', 'stale.md' => '']);
    $fake = new FakeProcessRunner(['batch-parse' => '']);

    $files = fakeBatch($fake, $input)
        ->withProviderOptions(LiteParseOptions::make()->maxPages(10)->withPassword('pw')->withDpi(200)->option('extract-images'))
        ->withProviderOptions(['extra' => ['extract-blocks' => true]])
        ->recursive()
        ->only('.PDF')
        ->withTimeout(300)
        ->saveTo($output.DIRECTORY_SEPARATOR);

    expect($files)->toBe([
        $output.DIRECTORY_SEPARATOR.'a.md',
        $output.DIRECTORY_SEPARATOR.'notes.md',
        $output.DIRECTORY_SEPARATOR.'sub'.DIRECTORY_SEPARATOR.'b.md',
        $output.DIRECTORY_SEPARATOR.'sub'.DIRECTORY_SEPARATOR.'deeper'.DIRECTORY_SEPARATOR.'c.md',
    ])->and($fake->recordedCommands()[0])->toBe([
        'lit', 'batch-parse', $input, $output.DIRECTORY_SEPARATOR, '--format', 'markdown', '-q',
        '--recursive', '--extension', 'pdf', '--max-pages', '10', '--password', 'pw', '--no-ocr', '--dpi', '200',
        '--extract-images', '--extract-blocks',
    ]);

    removeBatchDirectory($input);
    removeBatchDirectory($output);
});

it('stays non-recursive and unfiltered by default', function (): void {
    $input = batchDirectory(['a.pdf' => '', 'b.docx' => '', 'sub/c.pdf' => '']);
    $output = batchDirectory(['a.txt' => '', 'b.txt' => '', 'sub/c.txt' => '']);
    $fake = new FakeProcessRunner;

    $files = fakeBatch($fake, $input)->recursive()->recursive(false)->saveTo($output, 'txt');

    expect($files)->toBe([$output.DIRECTORY_SEPARATOR.'a.txt', $output.DIRECTORY_SEPARATOR.'b.txt'])
        ->and($fake->recordedCommands()[0])->toContain('--format', 'text')->not->toContain('--recursive', '--extension');

    removeBatchDirectory($input);
    removeBatchDirectory($output);
});

it('maps batch output formats and OCR options', function (string $format, string $flag, string $extension): void {
    $input = batchDirectory(['a.pdf' => '']);
    $output = batchDirectory(['a.'.$extension => '']);
    $fake = new FakeProcessRunner;

    $files = fakeBatch($fake, $input)
        ->withProviderOptions(LiteParseOptions::make()->withOcr('fra', serverUrl: 'http://ocr', workers: 4))
        ->saveTo($output, $format);

    expect($files)->toBe([$output.DIRECTORY_SEPARATOR.'a.'.$extension])
        ->and($fake->recordedCommands()[0])
        ->toContain('--format', $flag, '--ocr-language', 'fra', '--ocr-server-url', 'http://ocr', '--num-workers', '4')
        ->not->toContain('--no-ocr');

    removeBatchDirectory($input);
    removeBatchDirectory($output);
})->with([
    'markdown' => ['MARKDOWN', 'markdown', 'md'],
    'md alias' => ['md', 'markdown', 'md'],
    'text' => ['text', 'text', 'txt'],
    'json' => ['json', 'json', 'json'],
]);

it('rejects options that batch-parse has no flag for', function (): void {
    $input = batchDirectory();
    $fake = new FakeProcessRunner;

    expect(fn (): array => fakeBatch($fake, $input)->withProviderOptions(LiteParseOptions::make()->page(1)->withoutLinks())->saveTo($input))
        ->toThrow(InvalidProviderOptionsException::class, 'options are not supported for batch parsing: pages, links')
        ->and(fn (): array => fakeBatch($fake, $input)->withProviderOptions(['tessdata_path' => '/tess'])->saveTo($input))
        ->toThrow(InvalidProviderOptionsException::class, 'option is not supported for batch parsing: tessdata_path')
        ->and($fake->ranCount())->toBe(0);

    rmdir($input);
});

it('rejects unknown and cross-provider batch options', function (): void {
    expect(fn (): PendingBatch => Parsel::directory('/tmp')->withProviderOptions(['typo' => true]))
        ->toThrow(InvalidProviderOptionsException::class, 'typo')
        ->and(fn (): PendingBatch => Parsel::directory('/tmp')->withProviderOptions(AnyDocOptions::make()))
        ->toThrow(InvalidProviderOptionsException::class, 'anydoc');
});

it('validates the input and output directories before running', function (): void {
    $input = batchDirectory(['file.pdf' => '']);
    $fake = Parsel::fake();

    expect(fn (): array => Parsel::directory($input.DIRECTORY_SEPARATOR.'missing')->saveTo($input))
        ->toThrow(FilesystemException::class, 'Input directory')
        ->and(fn (): array => Parsel::directory($input.DIRECTORY_SEPARATOR.'file.pdf')->saveTo($input))
        ->toThrow(FilesystemException::class, 'Input directory')
        ->and(fn (): array => Parsel::directory($input)->saveTo($input.DIRECTORY_SEPARATOR.'file.pdf'))
        ->toThrow(FilesystemException::class, 'is not a directory')
        ->and($fake->ranCount())->toBe(0);

    removeBatchDirectory($input);
});

it('rejects empty paths, extensions and unknown formats', function (): void {
    expect(fn (): PendingBatch => Parsel::directory(''))->toThrow(InvalidArgumentException::class, 'input directory')
        ->and(fn (): PendingBatch => Parsel::directory('/tmp')->only('.'))->toThrow(InvalidArgumentException::class, 'extension')
        ->and(fn (): array => Parsel::directory('/tmp')->saveTo(''))->toThrow(InvalidArgumentException::class, 'output directory')
        ->and(fn (): array => Parsel::directory('/tmp')->saveTo('/tmp', 'html'))->toThrow(InvalidArgumentException::class, '"html"');
});

it('throws with per-file errors when any document fails', function (): void {
    $input = batchDirectory(['a.pdf' => '']);
    $fake = new FakeProcessRunner(['batch-parse' => new ProcessResult(1, '', "[liteparse] error parsing a.pdf\n[liteparse] batch complete: 0 succeeded, 1 failed", ['lit'])]);

    expect(fn (): array => fakeBatch($fake, $input)->saveTo($input))
        ->toThrow(ParseFailedException::class, '1 failed');

    removeBatchDirectory($input);
});

it('rejects batch parsing for drivers without the capability', function (): void {
    $fake = Parsel::fake();

    expect(fn (): array => Parsel::driver('anydoc')->directory('/missing')->saveTo('/tmp'))
        ->toThrow(UnsupportedCapabilityException::class, 'batch parsing')
        ->and($fake->ranCount())->toBe(0);
});
