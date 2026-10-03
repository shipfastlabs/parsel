<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\InvalidProviderOptionsException;
use Shipfastlabs\Parsel\Exceptions\ParseTimedOutException;
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

it('translates a timed out conversion into a parsel exception', function (): void {
    Parsel::fake(['anydoc' => new ProcessResult(143, '', '', ['anydoc'], 30.0)]);

    expect(fn (): string => Parsel::driver('anydoc')->file(fixture('sample.pdf'))->markdown())
        ->toThrow(ParseTimedOutException::class, 'anydoc timed out after 30 seconds.');
});
