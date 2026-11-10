<?php

namespace Splicewire\Beam\Mcp\Data;

use Schemastud\DataSchemas\Attributes\Description;
use Spatie\LaravelData\Data;

/**
 * The declared input shape of the `read_config_sections` MCP tool. DerivesInputSchemaFromData advertises these
 * constructor parameters as its `inputSchema`, and `handle()` hydrates this class from the request.
 * Scaffolded by `splicewire:beam:mcp:make:tool`.
 */
class ReadConfigSectionsInputData extends Data
{
    public function __construct(
        #[Description('A config root to read one section (e.g. `app.meters`). Omit it to read every section.')]
        public ?string $root = null,
    ) {}
}
