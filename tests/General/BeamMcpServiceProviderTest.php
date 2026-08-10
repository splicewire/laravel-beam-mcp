<?php

use Splicewire\Beam\Manifest\ManifestArity;
use Splicewire\Beam\Manifest\ManifestIndex;
use Splicewire\Beam\Manifest\ManifestSeam;
use Splicewire\Beam\Mcp\McpToolManifest;

test('McpToolManifest is bound as a container singleton', function () {
    expect(app(McpToolManifest::class))->toBe(app(McpToolManifest::class));
});

test('boot with no config configured is a no-op — the manifest boots empty', function () {
    expect(app(McpToolManifest::class)->all())->toBe([]);
});

test('boot describes McpToolManifest into beam-core\'s ManifestIndex', function () {
    $descriptor = collect(app(ManifestIndex::class)->descriptors())
        ->firstWhere('name', 'McpToolManifest');

    expect($descriptor)->not->toBeNull()
        ->and($descriptor->seam)->toBe(ManifestSeam::AttributeScan)
        ->and($descriptor->arity)->toBe(ManifestArity::RunAll)
        ->and($descriptor->package)->toBe('splicewire/laravel-beam-mcp')
        ->and($descriptor->where)->toContain(McpToolManifest::class);
});
