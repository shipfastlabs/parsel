<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class UrlDownloadException extends ParselException
{
    public static function failedToDownload(string $url): self
    {
        return new self(sprintf('Failed to download "%s".', $url));
    }
}
