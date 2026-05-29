<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class InvalidOutputException extends ParselException
{
    public static function emptyOutput(): self
    {
        return new self('liteparse returned empty output.');
    }

    public static function malformedJson(string $detail): self
    {
        return new self(sprintf('liteparse returned malformed JSON: %s', $detail));
    }
}
