<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class SourceNotFoundException extends ParselException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('The document "%s" does not exist.', $path));
    }
}
