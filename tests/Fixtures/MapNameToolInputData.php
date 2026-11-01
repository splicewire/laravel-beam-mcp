<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class MapNameToolInputData extends Data
{
    public function __construct(
        #[MapName('item_ref')]
        public string $itemRef,
    ) {}
}
