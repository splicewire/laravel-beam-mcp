<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;
use Rushing\McpRegistry\Concerns\AuthorizesTools;
use Splicewire\Beam\Mcp\Authorization\EntitlementToolAuthorizer;

/**
 * A minimal server wired to the beam entitlement authorizer — the ONE line a real host writes. It exists
 * so tool-listing omission can be asserted through `createContext()`, the chokepoint that feeds both
 * `tools/list` and `tools/call`.
 */
#[Name('Gated Shop')]
#[Version('1.0.0')]
class GatedShopServer extends Server
{
    use AuthorizesTools;

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        GatedShopTool::class,
        UngatedShopTool::class,
    ];

    protected function toolAuthorizer(): callable
    {
        return app(EntitlementToolAuthorizer::class);
    }
}
