<?php

namespace Splicewire\Beam\Mcp\Shapes;

use Attribute;

/**
 * Wire-schema metadata for one constructor-promoted property of an MCP input Data class — the
 * pieces {@see DataInputSchema} cannot read off the PHP type itself (description, array item type,
 * enum values). Type, wire name, required-ness, and default all come from the property declaration,
 * so this attribute never restates them.
 */
/*
 * TARGET_PROPERTY is required alongside TARGET_PARAMETER even though only the parameter is ever
 * read: on a constructor-PROMOTED property — the only place this attribute is ever written — PHP
 * attaches the attribute to BOTH the parameter and the generated property, and spatie's
 * `DataAttributesCollectionFactory` instantiates every property attribute it finds. Parameter-only
 * targeting therefore made `DataConfig::getDataClass()` throw "cannot target property" for any Data
 * class using `#[Field]` at all. Unreachable until api-surface-coherence ticket 85 booted
 * `LaravelDataServiceProvider` in this harness, because nothing here could analyse a Data class.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class Field
{
    /**
     * @param  string|null  $description  the property's advertised description
     * @param  string|null  $items  item type for array properties ('string', 'integer', 'number', 'boolean')
     * @param  array<int, string|int>|null  $enum  the allowed values, for enum-constrained properties
     */
    public function __construct(
        public ?string $description = null,
        public ?string $items = null,
        public ?array $enum = null,
    ) {}
}
