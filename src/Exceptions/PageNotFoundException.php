<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Exceptions;

final class PageNotFoundException extends ParselException
{
    public static function forNumber(int $number): self
    {
        return new self(sprintf('Page %d was not found in the document.', $number));
    }
}
