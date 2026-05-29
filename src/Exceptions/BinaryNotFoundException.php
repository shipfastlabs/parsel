<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class BinaryNotFoundException extends ParselException
{
    public static function onPath(string $name, string $envVar): self
    {
        return new self(sprintf(
            'Could not locate the "%s" binary. Set it explicitly with Parsel::usingBinary() or ->withBinary(), '
            .'export the %s environment variable, or make sure "%s" is on your PATH.',
            $name,
            $envVar,
            $name,
        ));
    }
}
