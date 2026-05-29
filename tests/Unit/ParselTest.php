<?php

declare(strict_types=1);

use Shipfastlabs\Parsel;
use Shipfastlabs\Parsel\PendingParse;
use Shipfastlabs\Parsel\Support\FakeProcessRunner;

it('creates a pending parse from a file with the real runner', function (): void {
    expect(Parsel::file('document.pdf'))->toBeInstanceOf(PendingParse::class);
});

it('creates a pending parse from bytes', function (): void {
    expect(Parsel::bytes('rawdata', 'pdf'))->toBeInstanceOf(PendingParse::class);
});

it('fakes parsing without requiring a real binary', function (): void {
    $fake = Parsel::fake(['--format text' => 'hello']);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('hello')
        ->and($fake)->toBeInstanceOf(FakeProcessRunner::class)
        ->and($fake->ranCount())->toBe(1);
});

it('keeps an explicitly configured binary when faking', function (): void {
    Parsel::usingBinary('/custom/lit');
    $fake = Parsel::fake(['--format text' => 'ok']);

    Parsel::file(fixture('sample.pdf'))->text();

    expect($fake->recordedCommands()[0][0])->toBe('/custom/lit');
});

it('swaps in a custom runner', function (): void {
    Parsel::swap(new FakeProcessRunner(['--format text' => 'swapped']));

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('swapped');
});

it('applies a default timeout', function (): void {
    Parsel::defaultTimeout(5.0);
    Parsel::fake(['--format text' => 'ok']);

    expect(Parsel::file(fixture('sample.pdf'))->text())->toBe('ok');

    Parsel::defaultTimeout(null);
});

it('flushes all configuration', function (): void {
    Parsel::usingBinary('/custom/lit');
    Parsel::fake();
    Parsel::flush();

    expect(Parsel::file('document.pdf'))->toBeInstanceOf(PendingParse::class);
});
