<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Contracts;

interface ExecutableFinder
{
    public function find(string $name): ?string;
}
