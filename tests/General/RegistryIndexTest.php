<?php

use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\RegistryIndex;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeRetrieveTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolOne;

// The tripwire (registry-kernel ticket 27 D3). A harness that does not boot PopcornServiceProvider
// still RESOLVES a RegistryIndex — a fresh one per make() — so every describe() below would land on
// a throwaway and every assertion in this file would pass over an empty index. This is the one test
// that fails when that happens.
test('the RegistryIndex is a shared singleton in this harness', function () {
    expect(app(RegistryIndex::class))->toBe(app(RegistryIndex::class));
});

test('beam.mcp.tools is in the index after boot, owned by this package', function () {
    $index = app(RegistryIndex::class);

    expect($index->has(Key::parse('beam.mcp.tools')))->toBeTrue()
        ->and($index->resolve(Key::parse('beam.mcp.tools')))->toBe(app(McpToolManifest::class));
});

test('a round trip through the port vocabulary still works after conforming', function () {
    $manifest = app(McpToolManifest::class);

    $manifest->registerClass(FakeShopToolOne::class);
    $manifest->registerClass(FakeRetrieveTool::class);

    expect($manifest->has('shop'))->toBeTrue()
        ->and($manifest->classesFor('shop'))->toBe([FakeShopToolOne::class])
        ->and($manifest->resolve('retrieve'))->toBe(FakeRetrieveTool::class)
        ->and($manifest->grouped())->toBe([
            'shop' => [FakeShopToolOne::class],
            'retrieve' => [FakeRetrieveTool::class],
        ]);
});

test('keys come back absolute, stamped with the declared root', function () {
    $manifest = app(McpToolManifest::class);
    $manifest->registerClass(FakeShopToolOne::class);

    expect(array_map('strval', $manifest->keys()))->toBe(['beam.mcp.tools.shop']);
});
