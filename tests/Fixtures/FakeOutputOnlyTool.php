<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tool;
use Splicewire\Beam\Mcp\Attributes\McpTool;

/** Declares an output schema but still hand-authors its input — only the input half is a finding. */
#[McpTool('shapes')]
class FakeOutputOnlyTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return ['query' => $schema->string()->required()];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return ['total' => $schema->integer()];
    }

    public function handle(): string
    {
        return 'ok';
    }
}
