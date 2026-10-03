<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
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
        ->and($fake->recordedCommands()[1])->toBe(['anydoc', '-', '--format', 'csv'])
        ->and($fake->recordedInputs())->toBe([null, 'a,b']);
});

it('streams byte sources with a known extension through stdin and lets anydoc sniff the format', function (string $extension): void {
    $fake = Parsel::fake(['anydoc' => '# Streamed']);

    $markdown = Parsel::driver('anydoc')->bytes('document-bytes', $extension)
        ->withProviderOptions(AnyDocOptions::make()->option('ocr', 'reject'))
        ->markdown();

    expect($markdown)->toBe('# Streamed')
        ->and($fake->recordedCommands())->toBe([['anydoc', '-', '--ocr', 'reject']])
        ->and($fake->recordedInputs())->toBe(['document-bytes']);
})->with(['pdf', 'docx', 'docm', 'xlsx', 'xls', 'pptx', 'ppsx', 'odt', 'epub', 'rtf']);

it('names signature-less byte formats explicitly when streaming through stdin', function (): void {
    $fake = Parsel::fake(['anydoc' => '| a |']);

    Parsel::driver('anydoc')->bytes("a\n1\n", '.CSV')->markdown();

    expect($fake->recordedCommands())->toBe([['anydoc', '-', '--format', 'csv']])
        ->and($fake->recordedInputs())->toBe(["a\n1\n"]);
});

it('streams bytes with an unknown extension through stdin when a format is given', function (): void {
    $fake = Parsel::fake(['anydoc' => 'ok']);

    Parsel::driver('anydoc')->bytes('a,b', 'txt')->withProviderOptions(AnyDocOptions::make()->format('csv'))->markdown();

    expect($fake->recordedCommands())->toBe([['anydoc', '-', '--format', 'csv']])
        ->and($fake->recordedInputs())->toBe(['a,b']);
});

it('falls back to a temporary file for bytes with an unknown extension and no format', function (): void {
    $fake = Parsel::fake(['anydoc' => 'ok']);

    Parsel::driver('anydoc')->bytes('%PDF-1.7', 'bin')->markdown();

    $command = $fake->recordedCommands()[0];

    expect($command)->toHaveCount(2)
        ->and($command[1])->toEndWith('.bin')
        ->and($command[1])->not->toBe('-')
        ->and(file_exists($command[1]))->toBeFalse()
        ->and($fake->recordedInputs())->toBe([null]);
});

it('throws when anydoc fails on stdin input', function (): void {
    Parsel::fake(['anydoc -' => new ProcessResult(1, '', 'anydoc: malformed document', ['anydoc', '-'])]);

    Parsel::driver('anydoc')->bytes('broken', 'docx')->markdown();
})->throws(ParseFailedException::class, 'malformed document');

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
