<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Support;

use Shipfastlabs\Parsel\Contracts\ExecutableFinder;
use Symfony\Component\Process\ExecutableFinder as SymfonyFinder;

/**
 * The default {@see ExecutableFinder}, backed by Symfony's cross-platform finder.
 */
final readonly class SymfonyExecutableFinder implements ExecutableFinder
{
    public function __construct(
        private SymfonyFinder $finder = new SymfonyFinder,
    ) {}

    public function find(string $name): ?string
    {
        return $this->finder->find($name);
    }
}
