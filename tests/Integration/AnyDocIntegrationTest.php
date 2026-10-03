<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\ParseFailedException;
use Shipfastlabs\Parsel\Exceptions\ParserUsageException;
use Shipfastlabs\Parsel\Options\AnyDocOptions;

beforeEach(function (): void {
    requireBinary($this, 'anydoc', 'PARSEL_ANYDOC_BINARY');
});

function anydocSample(string $extension): string
{
    return __DIR__.'/../../examples/docs/sample.'.$extension;
}

it('converts a real document to markdown with anydoc', function (): void {
    $markdown = Parsel::driver('anydoc')
        ->file(anydocSample('docx'))
        ->markdown();

    expect($markdown)->toContain('Heading 1');
})->group('integration');

it('converts a real spreadsheet to markdown with anydoc', function (): void {
    $markdown = Parsel::driver('anydoc')
        ->file(anydocSample('xlsx'))
        ->markdown();

    expect($markdown)->toContain('Contoso Sales Report');
})->group('integration');

it('names the input format with --format', function (string $extension, string $format, string $expected): void {
    $markdown = Parsel::driver('anydoc')
        ->file(anydocSample($extension))
        ->withProviderOptions(AnyDocOptions::make()->format($format))
        ->markdown();

    expect($markdown)->toContain($expected);
})->with([
    'docx' => ['docx', 'docx', 'Heading 1'],
    'pdf with a normalized extension' => ['pdf', '.PDF', 'UNITED STATES'],
])->group('integration');

it('surfaces a rejected --format as a usage error', function (): void {
    $parse = fn (): string => Parsel::driver('anydoc')
        ->file(anydocSample('docx'))
        ->withProviderOptions(AnyDocOptions::make()->format('bogus'))
        ->markdown();

    expect($parse)->toThrow(ParserUsageException::class, "invalid format 'bogus'")
        ->and($parse)->toThrow(ParseFailedException::class, 'anydoc exited with code 2');
})->group('integration');

it('streams real document bytes to anydoc through stdin', function (string $extension, string $expected): void {
    $bytes = (string) file_get_contents(anydocSample($extension));

    $markdown = Parsel::driver('anydoc')->bytes($bytes, $extension)->markdown();

    expect($markdown)->toContain($expected);
})->with([
    'docx' => ['docx', 'This text has formatting directly applied'],
    'xlsx' => ['xlsx', '## Template'],
    'pdf' => ['pdf', 'UNITED STATES'],
])->group('integration');

it('streams real csv bytes to anydoc with an inferred format', function (): void {
    $markdown = Parsel::driver('anydoc')->bytes("name,qty\nwidget,2\n", 'csv')->markdown();

    expect($markdown)->toContain('| name | qty |', '| widget | 2 |');
})->group('integration');
