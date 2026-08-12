<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Splicewire\Beam\Mcp\Attributes\McpTool;

/** Declares an output schema and takes no input — fully conformant, so it must produce no finding. */
#[McpTool('shapes')]
class FakeDeclaredTool extends Tool
{
    public function outputSchema(JsonSchema $schema): array
    {
        return ['total' => $schema->integer()];
    }

    public function handle(): string
    {
        return 'ok';
    }
}
