<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server\Tool;
use Rushing\McpRegistry\Attributes\ToolAbility;

/**
 * A tool GATED on a flat namespaced ability — the shape the entitlement plane already speaks. Its ability
 * doubles as a beam entitlement key (`shop.write` → the `entitlement:shop.write` Gate ability), which is
 * the whole point: no translation table, one decision.
 */
#[ToolAbility('shop.write')]
class GatedShopTool extends Tool
{
    public function handle(): string
    {
        return 'wrote';
    }
}
