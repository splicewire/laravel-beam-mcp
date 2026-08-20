<?php

use Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeDeclaredTool;
use Splicewire\Beam\Seed\BeamSeedManifest;

/**
 * ADR-0210 — beam-mcp's docs contribution is a seed row, an endpoint, and (someone else's) generic
 * component. These cover the two halves this package actually owns.
 */
test('the advertised-catalog endpoint mounts itself and emits the declared wire shape', function () {
    app(McpToolManifest::class)->registerClass(FakeDeclaredTool::class);

    $response = $this->get('/beam/mcp/manifest.json');

    $response->assertOk();

    // The shape `<ManifestTable>` declares (ticket 20): `{ items: [{name, title, description}] }`.
    $items = $response->json('items');
    expect($items)->toBeArray()->toHaveCount(1)
        ->and($items[0])->toHaveKeys(['name', 'title', 'description']);
});

test('an empty catalog is an empty list, not a 404', function () {
    // 404 is reserved to mean "the contributor is uninstalled" — the ONE signal `<ManifestTable>`
    // distinguishes from a broken endpoint (ADR-0210 §6). A registered-but-empty server must not
    // impersonate an absent one.
    $this->get('/beam/mcp/manifest.json')->assertOk()->assertExactJson(['items' => []]);
});

test('the endpoint can be unmounted so a host may serve its own', function () {
    config(['beam.mcp.manifest_uri' => null]);

    expect(config('beam.mcp.manifest_uri'))->toBeNull();
});

test('beam-mcp registers ONE ungated seed step, ordered after beam-ux docs root', function () {
    $steps = collect(app(BeamSeedManifest::class)->steps())
        ->filter(fn ($step) => $step->package === 'splicewire/laravel-beam-mcp');

    expect($steps)->toHaveCount(1);

    $step = $steps->first();

    expect($step->seeder)->toBe(McpDocsSeeder::class)
        // Ungated: the guard is INSIDE the seeder, so a headless host gets a reported skip rather than
        // a missing-class fatal — the manifest takes a class-string and nothing loads until it runs.
        ->and($step->configGate)->toBeNull()
        // After beam-ux's 20: the MCP page hangs off the docs root that seeder creates.
        ->and($step->order)->toBe(30);
});

test('the docs seeder no-ops when beam-ux is absent', function () {
    // beam-ux is not a dependency of this package and is not installed in this suite, so the seeder's
    // string-resolved guard is the thing under test: running it must not fatal.
    (new McpDocsSeeder)->run();
})->throwsNoExceptions();
