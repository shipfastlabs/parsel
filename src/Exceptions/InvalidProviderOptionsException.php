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

    public static function forProvider(string $expected, string $actual): self
    {
        return new self(sprintf('Options for provider [%s] cannot be used with driver [%s].', $actual, $expected));
    }

    /**
     * @param  list<string>  $keys
     */
    public static function unsupported(string $provider, string $operation, array $keys): self
    {
        return new self(sprintf(
            'The %s provider option%s %s not supported for %s: %s.',
            $provider,
            count($keys) === 1 ? '' : 's',
            count($keys) === 1 ? 'is' : 'are',
            $operation,
            implode(', ', $keys),
        ));
    }
}
