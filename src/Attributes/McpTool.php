<?php

namespace Splicewire\Beam\Mcp\Attributes;

use Attribute;
use Splicewire\Beam\Mcp\McpToolManifest;

/**
 * Marks a `Laravel\Mcp\Server\Tool` class as belonging to a named MOUNT GROUP, e.g.
 * `#[McpTool('shop')] class CreateCheckoutTool extends Tool { ... }`.
 *
 * Boot-time discovery ({@see McpToolManifest}) reflects annotated classes
 * into grouped tool-class lists a host MCP server (e.g. `SplicewireServer::groups()`) reads at
 * `groups()` time — so a beam-tier package exposes an MCP tool just by carrying this attribute,
 * no central host-side edit.
 *
 * Orthogonal to `Rushing\McpRegistry\Attributes\ToolAbility` (the AUTHORIZATION axis — which
 * ability gates the tool) exactly as `rushing/laravel-mcp-registry`'s own `ToolGroup` is
 * orthogonal to it: a tool class may carry BOTH attributes independently. This attribute only
 * decides which group the class MOUNTS into; it says nothing about who may call it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class McpTool
{
    /**
     * @param  string  $group  the mount-group name this tool belongs to (e.g. 'shop', 'retrieve')
     */
    public function __construct(
        public string $group,
    ) {}
}
