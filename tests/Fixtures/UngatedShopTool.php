<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Tool;

/** No #[ToolAbility] — ungated, and it must STAY reachable no matter what the gate says. */
class UngatedShopTool extends Tool
{
    public function handle(): string
    {
        return 'read';
    }
}
