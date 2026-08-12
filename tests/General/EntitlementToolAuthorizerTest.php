<?php

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Splicewire\Beam\Authorization\AbilityResolver;
use Splicewire\Beam\Authorization\ActorPort;
use Splicewire\Beam\Mcp\Authorization\EntitlementToolAuthorizer;
use Splicewire\Beam\Mcp\Tests\Fixtures\GatedShopServer;

/**
 * particle-doctrine-convergence ticket 09 — the MCP HALF of the cross-transport ability resolver.
 *
 * The resolver has NO unit seam of its own on purpose: it is tested through the two transports it serves.
 * The HTTP half (a forbidden response) lives in beam-core's `HttpTransportAbilityTest`; this file pins the
 * MCP dialect for "no" — the tool is absent from the listing, and therefore refused if called, because
 * `AuthorizesTools` filters inside `createContext()`, the single context both `tools/list` and
 * `tools/call` read.
 *
 * The `entitlement:{key}` Gate abilities beam's provider registers at boot are declared directly here
 * instead of booting the whole of beam-core: this package's TestCase deliberately avoids that weight, the
 * registration itself is beam-core's own tested concern, and what is under test here is that the MCP
 * transport CONSULTS the gate rather than keeping an ability set of its own.
 */
function defineShopEntitlement(array $granted): void
{
    Gate::define('entitlement:shop.write', fn ($user = null) => in_array(
        $user instanceof PrivilegedShopUser ? 'shop.write' : 'nothing',
        $granted,
        true,
    ));
}

function actorPort(mixed $actor): void
{
    app()->instance(ActorPort::class, new class($actor) implements ActorPort
    {
        public function __construct(private mixed $actor) {}

        public function actor(): mixed
        {
            return $this->actor;
        }
    });
}

function listedTools(): array
{
    return (new GatedShopServer(new FakeTransporter))
        ->createContext()
        ->tools()
        ->map(fn ($tool) => $tool->name())
        ->all();
}

class PrivilegedShopUser extends User {}

class PlainShopUser extends User {}

it('omits a gated tool from the listing when the entitlement gate denies the actor', function () {
    defineShopEntitlement(['shop.write']);
    actorPort(new PlainShopUser);

    expect(listedTools())->not->toContain('gated-shop-tool');
});

it('lists a gated tool when the entitlement gate grants the actor', function () {
    defineShopEntitlement(['shop.write']);
    actorPort(new PrivilegedShopUser);

    expect(listedTools())->toContain('gated-shop-tool');
});

it('keeps an ungated tool reachable whatever the gate says', function () {
    // The regression floor: adopting the gate must not silently narrow a surface that was never gated.
    defineShopEntitlement([]);
    actorPort(null);

    expect(listedTools())->toContain('ungated-shop-tool')
        ->and(listedTools())->not->toContain('gated-shop-tool');
});

it('denies a gated tool when no entitlement ability is registered at all (deny-default)', function () {
    // No Gate::define — an unknown ability is not a grant.
    actorPort(new PrivilegedShopUser);

    expect(listedTools())->not->toContain('gated-shop-tool')
        ->and(listedTools())->toContain('ungated-shop-tool');
});

it('answers the same decision the shared resolver and the entitlement gate answer for the same actor', function () {
    // The cross-transport invariant. Both transports route the SAME actor + ability through the SAME
    // AbilityResolver instance; the HTTP half asserts its controller obeys that resolver, and this asserts
    // the MCP listing obeys it too. Nothing between the two can hold a second ability set.
    defineShopEntitlement(['shop.write']);

    $resolver = app(AbilityResolver::class);
    $gate = app(GateContract::class);

    foreach ([new PrivilegedShopUser, new PlainShopUser, null] as $actor) {
        actorPort($actor);

        $viaGate = $gate->forUser($actor)->allows('entitlement:shop.write');
        $viaResolver = $resolver->allows($actor, 'shop.write');
        $viaTransport = in_array('gated-shop-tool', listedTools(), true);

        expect($viaResolver)->toBe($viaGate)
            ->and($viaTransport)->toBe($viaGate);
    }
});

it('ignores the tool class so the gate stays instance-blind across transports', function () {
    defineShopEntitlement(['shop.write']);
    actorPort(new PrivilegedShopUser);

    $authorizer = app(EntitlementToolAuthorizer::class);

    expect($authorizer('shop.write', 'Any\\Tool\\At\\All'))->toBeTrue()
        ->and($authorizer('shop.write'))->toBeTrue()
        ->and($authorizer('shop.read', 'Any\\Tool\\At\\All'))->toBeFalse();
});
