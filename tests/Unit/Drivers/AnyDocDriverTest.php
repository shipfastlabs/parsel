<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\OcrRequiredException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\ParserUsageException;
use Shipfastlabs\Parsel\Options\AnyDocOptions;
use Shipfastlabs\Parsel\Support\ProcessResult;

it('converts file and byte sources to trimmed markdown', function (): void {
    $fake = Parsel::fake(['anydoc' => "\n# AnyDoc\n"]);

    $file = Parsel::driver('anydoc')->file(fixture('sample.pdf'))
        ->withProviderOptions(AnyDocOptions::make()->format('PDF')->withBinary('/custom/anydoc')->option('future'))
        ->markdown();
    $bytes = Parsel::driver('anydoc')->bytes('a,b', 'csv')
        ->withProviderOptions(['format' => 'csv'])
        ->markdown();

    expect($file)->toBe('# AnyDoc')->and($bytes)->toBe('# AnyDoc')
        ->and($fake->recordedCommands()[0])->toContain('/custom/anydoc', '--format', 'pdf', '--future')
        ->and($fake->recordedCommands()[1])->toContain('--format', 'csv');
});

it('omits false raw flags and renders scalar raw flags', function (): void {
    $fake = Parsel::fake(['anydoc' => 'ok']);

    Parsel::driver('anydoc')->file(fixture('sample.pdf'))
        ->withProviderOptions(AnyDocOptions::make()->option('workers', 2)->option('debug', false))
        ->markdown();

    expect($fake->recordedCommands()[0])->toContain('--workers', '2')->not->toContain('--debug');
});

it('safely ignores malformed raw array entries', function (): void {
    $fake = Parsel::fake(['anydoc' => 'ok']);

    Parsel::driver('anydoc')->file(fixture('sample.pdf'))
        ->withProviderOptions(['extra' => [true, 'ratio' => 1.5]])
        ->markdown();

    expect($fake->recordedCommands()[0])->not->toContain('--0', '--ratio');
});

it('rejects unknown array options', function (): void {
    Parsel::driver('anydoc')->file('report.docx')->withProviderOptions(['typo' => true]);
})->throws(InvalidProviderOptionsException::class, 'typo');

it('maps documented anydoc exit codes to specific exceptions', function (int $exitCode, string $stderr, string $exception): void {
    Parsel::fake(['anydoc' => new ProcessResult($exitCode, '', $stderr, ['anydoc', 'report.pdf'])]);

    try {
        Parsel::driver('anydoc')->file(fixture('sample.pdf'))->markdown();
        $this->fail('Expected the conversion to fail.');
    } catch (ParseFailedException $parseFailedException) {
        expect($parseFailedException)->toBeInstanceOf($exception)
            ->and($parseFailedException::class)->toBe($exception)
            ->and($parseFailedException->exitCode)->toBe($exitCode)
            ->and($parseFailedException->stderr)->toBe($stderr)
            ->and($parseFailedException->getMessage())->toContain('anydoc exited with code '.$exitCode);
    }
})->with([
    'unreadable document' => [1, "anydoc: io error\n", ParseFailedException::class],
    'usage error' => [2, "anydoc: invalid format 'bogus'\n", ParserUsageException::class],
    'OCR required' => [3, "anydoc: page 1 of 1 needs OCR\n", OcrRequiredException::class],
]);

it('suggests OCR alternatives when anydoc reports scanned pages', function (): void {
    Parsel::fake(['anydoc' => new ProcessResult(3, '', "anydoc: page 1 of 1 needs OCR\n", ['anydoc'])]);

    Parsel::driver('anydoc')->file(fixture('sample.pdf'))->markdown();
})->throws(OcrRequiredException::class, "needs OCR. Scanned or image-only pages need OCR, which this driver does not perform locally. Use AnyDoc hosted OCR with AnyDocOptions::make()->option('ocr', 'hosted'), or parse the document with the liteparse driver and LiteParseOptions::make()->withOcr().");
