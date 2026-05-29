<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Exceptions\BinaryNotFoundException;
use Shipfastlabs\Parsel\Support\BinaryResolver;
use Tests\Doubles\FakeExecutableFinder;

it('returns an explicit path before anything else', function (): void {
    expect(new BinaryResolver(new FakeExecutableFinder('/found/lit'))->resolve('/explicit/lit'))
        ->toBe('/explicit/lit');
});

it('reads the environment variable when no explicit path is given', function (): void {
    putenv('PARSEL_LIT_BINARY=/env/lit');

    expect(new BinaryResolver(new FakeExecutableFinder('/found/lit'))->resolve())->toBe('/env/lit');

    putenv('PARSEL_LIT_BINARY');
});

it('falls back to a PATH lookup', function (): void {
    expect(new BinaryResolver(new FakeExecutableFinder('/path/lit'))->resolve())->toBe('/path/lit');
});

it('throws when the binary cannot be resolved anywhere', function (): void {
    new BinaryResolver(new FakeExecutableFinder)->resolve();
})->throws(BinaryNotFoundException::class);
