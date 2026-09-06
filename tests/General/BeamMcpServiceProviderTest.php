<?php

use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Splicewire\Beam\Mcp\McpToolManifest;

test('McpToolManifest is bound as a container singleton', function () {
    expect(app(McpToolManifest::class))->toBe(app(McpToolManifest::class));
});

test('boot with no config configured is a no-op — the manifest boots empty', function () {
    expect(app(McpToolManifest::class)->all())->toBe([]);
});

// Registry-kernel ticket 21: the obligation moved from "push a descriptor into beam-core's index from
// this provider" to "declare it on the class". That is why this asserts on the CLASS and needs no beam
// binding at all — the package no longer reaches into another package's singleton to be listed.
test('McpToolManifest declares itself with #[IsRegistry]', function () {
    $declaration = IsRegistry::of(McpToolManifest::class);

    expect($declaration)->not->toBeNull()
        ->and($declaration->root)->toBe('beam.mcp.tools')
        ->and((string) Key::parse($declaration->root))->toBe('beam.mcp.tools');
});
