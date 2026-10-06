<?php

use Laravel\Mcp\Facades\Mcp;
use Splicewire\Beam\Mcp\Docs\McpEndpoints;
use Splicewire\Beam\Mcp\Tests\Fixtures\GatedShopServer;

/**
 * docs-walkthrough DOC-11(d), DOCS-08: the MCP endpoint a docs page tells a reader to copy comes from the route table
 * (`Mcp::servers()`), never a guess. Before this the seeder printed `app.url` + `/mcp` on every host, and all three
 * live hosts answered 404 there (`docs-known-dead.json`: beam.test, splicewire.test, app.splicewire.test).
 */
beforeEach(function () {
    config(['app.url' => 'https://host.test']);
});

test('no mounted server says so, and prints no URL to copy', function () {
    $endpoints = app(McpEndpoints::class);

    expect($endpoints->all())->toBe([])
        ->and($endpoints->markdown())->toContain('No MCP server is mounted on this deployment')
        ->and($endpoints->markdown())->not->toContain('https://');
});

test('a central server is its absolute URL, with the guard named and a client key from the server class', function () {
    Mcp::web('mcp/shop', GatedShopServer::class)->middleware('auth:sanctum');

    [$server] = app(McpEndpoints::class)->all();

    expect($server)->toMatchArray(['url' => 'https://host.test/mcp/shop', 'guard' => 'sanctum', 'key' => 'gated-shop'])
        ->and(app(McpEndpoints::class)->markdown())->toContain('https://host.test/mcp/shop')
        ->toContain('sanctum')
        ->toContain('"gated-shop"');
});

test('a server under subdomain tenancy is a {tenant} template on the central domain', function () {
    config(['tenancy.central_domains' => ['app.test']]);
    Mcp::web('mcp', GatedShopServer::class)->middleware(['auth:api', 'Stancl\\Tenancy\\Middleware\\InitializeTenancyBySubdomain']);

    [$server] = app(McpEndpoints::class)->all();

    expect($server['url'])->toBe('https://{tenant}.app.test/mcp')
        ->and($server['guard'])->toBe('api')
        ->and(app(McpEndpoints::class)->markdown())->toContain('{tenant}');
});

test('the seeded page interpolates the route table: none mounted, then a mounted server', function () {
    @unlink(resource_path('beam-mcp/docs/mcp.mdx'));
    $stub = fn () => (new ReflectionMethod($seeder = new Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder, 'stub'))->invoke($seeder)['body'];

    expect($stub())->toContain('No MCP server is mounted on this deployment')->not->toContain('{{ mcp_servers }}');

    Mcp::web('mcp/shop', GatedShopServer::class)->middleware('auth:sanctum');
    expect($stub())->toContain('https://host.test/mcp/shop')->toContain('`sanctum` guard');
});

test('one server class mounted twice gets two distinct client keys, so neither overwrites the other', function () {
    Mcp::web('mcp', GatedShopServer::class)->middleware('auth:api');
    Mcp::web('mcp-local', GatedShopServer::class);

    expect(array_column(app(McpEndpoints::class)->all(), 'key'))->toBe(['gated-shop', 'gated-shop-mcp-local'])
        ->and(app(McpEndpoints::class)->markdown())->toContain('"gated-shop-mcp-local"');
});

test('the legacy single endpoint prefers a guarded server over a keyless local one', function () {
    Mcp::web('mcp-local', GatedShopServer::class);
    Mcp::web('mcp', GatedShopServer::class)->middleware('auth:api');

    expect(app(McpEndpoints::class)->primary())->toBe('https://host.test/mcp');
});
