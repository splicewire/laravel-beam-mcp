<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Mcp\Shapes\Field;

/**
 * A stand-in for the input Data class an MCP tool declares (`particle.undeclared-mcp-shape`), sized
 * to exercise every branch `Shapes\DataInputSchema` reads: an explicitly mapped wire name, a
 * property left to the AMBIENT input mapper, a `Field`-annotated array, and an optional with a
 * non-null default.
 *
 * This package declares no `Data` subclass of its own, so without a fixture the laravel-data
 * harness fix (api-surface-coherence ticket 85) would have nothing to execute against and the pin
 * would assert config alone.
 */
class FakeToolInputData extends Data
{
    public function __construct(
        #[MapInputName('shop_id')]
        public string $shopId,

        // Deliberately NOT mapped: its wire name under the host is decided by
        // `data.name_mapping_strategy.input`, which is the regime the harness must mirror.
        public string $productSku,

        #[Field(description: 'Tags to filter by', items: 'string')]
        public array $tags = [],

        #[Field(description: 'Maximum rows to return')]
        public int $limit = 25,
    ) {}
}
