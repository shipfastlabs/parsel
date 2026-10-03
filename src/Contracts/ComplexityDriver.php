<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Contracts;

use Shipfastlabs\Parsel\Data\DocumentComplexity;
use Shipfastlabs\Parsel\ParseRequest;

interface ComplexityDriver extends Driver
{
    public function complexity(ParseRequest $request): DocumentComplexity;
}
