<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class BothMappingAttributesToolInputData extends Data
{
    public function __construct(
        #[MapInputName('in_key')]
        #[MapName('both_key')]
        public string $bothKey,
    ) {}
}
