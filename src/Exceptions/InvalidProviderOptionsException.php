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

    /**
     * @param  list<string>  $allowed
     */
    public static function invalidValue(string $provider, string $key, mixed $value, array $allowed): self
    {
        return new self(sprintf(
            'Invalid %s provider option [%s]: %s. Expected one of: %s.',
            $provider,
            $key,
            is_scalar($value) ? var_export($value, true) : get_debug_type($value),
            implode(', ', $allowed),
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
