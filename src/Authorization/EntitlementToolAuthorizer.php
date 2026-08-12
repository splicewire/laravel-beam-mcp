<?php

namespace Splicewire\Beam\Mcp\Authorization;

use Laravel\Mcp\Server\Tool;
use Rushing\McpRegistry\Attributes\ToolAbility;
use Rushing\McpRegistry\Concerns\AuthorizesTools;
use Splicewire\Beam\Authorization\AbilityResolver;
use Splicewire\Beam\Authorization\ActorPort;

/**
 * particle-doctrine-convergence ticket 09 — the MCP HALF of the cross-transport ability resolver.
 *
 * Drop this into a server's `toolAuthorizer()` and the MCP transport stops maintaining an ability set of
 * its own:
 *
 *     protected function toolAuthorizer(): callable
 *     {
 *         return app(EntitlementToolAuthorizer::class);
 *     }
 *
 * ## Why this is a thin adapter and NOT a new abstraction
 *
 * The entitlement plane was already transport-neutral before ticket 09 existed. beam registers one
 * `entitlement:{key}` Gate ability per known feature key, subject-free and actor-optional — which is
 * structurally the same object as a {@see ToolAbility}: a flat namespaced string answered by set
 * membership. So there is nothing to translate. This class holds no ability list, no mapping table and
 * no cache; it forwards `(ability)` to the shared {@see AbilityResolver} with NO subject, which lands on
 * the entitlement branch, which is the gate. Anything more would be a second copy of a decision that
 * already has one home — the exact drift the ticket exists to prevent.
 *
 * The per-action particle plane is the half that genuinely does not port over, and this class makes no
 * attempt to fake it: that plane requires a loaded subject and speaks bare policy verbs, while the MCP
 * gate is deliberately instance-blind. A tool that needs per-instance authorization must ask inside its
 * own `handle()`, where a subject exists.
 *
 * ## Deny shape stays the transport's
 *
 * This returns a bool and constructs nothing. {@see AuthorizesTools} filters `$this->tools` inside
 * `createContext()` — the single chokepoint feeding both `tools/list` and `tools/call` — so a refused
 * tool simply is not there: absent from the listing and refused if called anyway. That is MCP's dialect
 * for "no", and it is not a 403.
 *
 * ## Tools with no {@see ToolAbility} are never consulted
 *
 * `AuthorizesTools` short-circuits an ungated tool before reaching any authorizer, so adopting this
 * cannot narrow an existing ungated surface. Only a tool that already declared an ability changes
 * behaviour, and only to answer from the gate instead of a hand-kept list.
 *
 * ## Command-line invocation is UNGATED, by policy
 *
 * A command projected as an MCP tool is gated on THIS path only. `php artisan <command>` from a shell
 * never reaches this class and is always allowed — a deliberate trusted-shell policy, stated rather
 * than left implicit. See {@see AbilityResolver} for the full statement.
 */
class EntitlementToolAuthorizer
{
    public function __construct(
        protected AbilityResolver $abilities,
        protected ActorPort $actor,
    ) {}

    /**
     * Answer the `callable(string $ability, class-string<Tool> $toolClass): bool` shape
     * {@see AuthorizesTools::toolAuthorizer()} expects.
     *
     * `$toolClass` is accepted and deliberately IGNORED: the gate is instance-blind, so admitting the
     * class into the decision would make the two transports answer different questions. It stays in the
     * signature because the seam passes it and a host override may care.
     *
     * @param  class-string<Tool>|string  $toolClass
     */
    public function __invoke(string $ability, string $toolClass = ''): bool
    {
        return $this->abilities->allows($this->actor->actor(), $ability);
    }
}
