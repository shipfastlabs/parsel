<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class FilesystemException extends ParselException
{
    public static function unableToWrite(string $path): self
    {
        return new self(sprintf('Unable to write to "%s".', $path));
    }
}
