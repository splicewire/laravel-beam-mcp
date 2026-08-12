<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Splicewire\Beam\Mcp\Attributes\McpTool;

/**
 * Hand-authors its input schema and declares no output — the shape essentially every live tool in the estate
 * has, and therefore the case the audit exists to count.
 */
#[McpTool('shapes')]
class FakeHandAuthoredTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->required()];
    }

    public function handle(): string
    {
        return 'ok';
    }
}
