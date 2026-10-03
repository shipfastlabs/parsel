<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

use Shipfastlabs\Parsel\Support\ProcessResult;

final class OcrRequiredException extends ParseFailedException
{
    protected static function describe(ProcessResult $result, string $driver): string
    {
        return rtrim(parent::describe($result, $driver), '.')
            .'. Scanned or image-only pages need OCR, which this driver does not perform locally. '
            .'Use AnyDoc hosted OCR with AnyDocOptions::make()->withHostedOcr(), '
            .'or parse the document with the liteparse driver and LiteParseOptions::make()->withOcr().';
    }
}
