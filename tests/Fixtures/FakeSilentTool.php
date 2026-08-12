<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Tool;
use Splicewire\Beam\Mcp\Attributes\McpTool;

/**
 * Declares neither schema. Taking no input is a complete contract, so the input half stays quiet — but the
 * tool still RETURNS something, so its absent output schema is a genuine undeclared shape.
 */
#[McpTool('shapes')]
class FakeSilentTool extends Tool
{
    public function handle(): string
    {
        return 'ok';
    }
}
