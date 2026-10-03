<?php

declare(strict_types=1);

namespace Shipfastlabs\Parsel\Contracts;

use Shipfastlabs\Parsel\BatchRequest;

interface BatchDriver extends Driver
{
    /**
     * @return list<string> The written output files.
     */
    public function batch(BatchRequest $request): array;
}
