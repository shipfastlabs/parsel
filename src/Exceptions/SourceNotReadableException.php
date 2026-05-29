<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class SourceNotReadableException extends ParselException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('The document "%s" exists but is not readable.', $path));
    }
}
