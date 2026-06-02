<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class UrlDownloadException extends ParselException
{
    public static function failedToDownload(string $url): self
    {
        return new self(sprintf('Failed to download "%s".', $url));
    }

    public static function httpError(string $url, int $statusCode): self
    {
        return new self(sprintf('Remote server returned HTTP %d for "%s".', $statusCode, $url));
    }
}
