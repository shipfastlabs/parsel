<?php

declare(strict_types=1);

use Shipfastlabs\Parsel\Support\SymfonyExecutableFinder;

it('finds an executable that is on the PATH', function (): void {
    expect((new SymfonyExecutableFinder)->find('php'))->not->toBeNull();
});

it('returns null for an executable that does not exist', function (): void {
    expect((new SymfonyExecutableFinder)->find('parsel-definitely-not-a-real-binary'))->toBeNull();
});
