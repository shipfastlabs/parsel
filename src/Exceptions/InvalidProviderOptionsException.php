<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class InvalidProviderOptionsException extends ParselException
{
    /**
     * @param  list<string>  $keys
     */
    public static function unknown(string $provider, array $keys): self
    {
        return new self(sprintf(
            'Unknown %s provider option%s: %s.',
            $provider,
            count($keys) === 1 ? '' : 's',
            implode(', ', $keys),
        ));
    }

    public static function invalidOcrServerHeader(string $name): self
    {
        return new self(sprintf('Invalid OCR server header [%s]: the name must be non-empty without a colon, and the header may not contain line breaks.', $name));
    }

    public static function forProvider(string $expected, string $actual): self
    {
        return new self(sprintf('Options for provider [%s] cannot be used with driver [%s].', $actual, $expected));
    }
}
