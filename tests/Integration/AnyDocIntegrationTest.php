<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\Support\BinaryResolver;

function anydocAvailable(): bool
{
    try {
        new BinaryResolver(name: 'anydoc', envVar: 'PARSEL_ANYDOC_BINARY')->resolve();

        return true;
    } catch (BinaryNotFoundException) {
        return false;
    }
}

it('converts a real document to markdown with anydoc', function (): void {
    if (! anydocAvailable()) {
        $this->markTestSkipped('anydoc binary not installed');
    }

    $markdown = Parsel::driver('anydoc')
        ->file(__DIR__.'/../../examples/docs/sample.docx')
        ->markdown();

    expect($markdown)->not->toBeEmpty();
})->group('integration');

it('streams real document bytes to anydoc through stdin', function (string $extension, string $expected): void {
    if (! anydocAvailable()) {
        $this->markTestSkipped('anydoc binary not installed');
    }

    $bytes = (string) file_get_contents(__DIR__.'/../../examples/docs/sample.'.$extension);

    $markdown = Parsel::driver('anydoc')->bytes($bytes, $extension)->markdown();

    expect($markdown)->toContain($expected);
})->with([
    'docx' => ['docx', 'This text has formatting directly applied'],
    'xlsx' => ['xlsx', '## Template'],
    'pdf' => ['pdf', 'UNITED STATES'],
])->group('integration');

it('streams real csv bytes to anydoc with an inferred format', function (): void {
    if (! anydocAvailable()) {
        $this->markTestSkipped('anydoc binary not installed');
    }

    $markdown = Parsel::driver('anydoc')->bytes("name,qty\nwidget,2\n", 'csv')->markdown();

    expect($markdown)->toContain('| name | qty |', '| widget | 2 |');
})->group('integration');
