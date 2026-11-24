<?php

namespace Splicewire\Beam\Mcp\Config;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Splicewire\Beam\Config\Attributes\ConfigKey;

/**
 * `beam.mcp /docs` (generated with {@see BeamMcpSection}).
 */
class BeamMcpDocsConfig extends Data
{
    public function __construct(
        #[Title('Seed docs page'), Description('Whether to seed the MCP documentation page when the docs package is installed.'), ConfigKey(env: 'BEAM_MCP_SEED_DOCS')]
        public bool|string $seed,
    ) {
    }
}
