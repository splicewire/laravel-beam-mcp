<?php

namespace Splicewire\Beam\Mcp\Shapes;

use Attribute;

/**
 * Wire-schema metadata for one constructor-promoted property of an MCP input Data class — the
 * pieces {@see DataInputSchema} cannot read off the PHP type itself (description, array item type,
 * enum values). Type, wire name, required-ness, and default all come from the property declaration,
 * so this attribute never restates them.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
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
