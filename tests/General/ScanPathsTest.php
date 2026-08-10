<?php

use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeRetrieveTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolOne;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolTwo;

test('scanPaths finds every #[McpTool]-annotated class under a directory, ignoring the rest', function () {
    $manifest = new McpToolManifest;

    $found = $manifest->scanPaths([__DIR__.'/../Fixtures']);

    expect($found)->toContain(FakeShopToolOne::class)
        ->toContain(FakeShopToolTwo::class)
        ->toContain(FakeRetrieveTool::class)
        ->not->toContain('Splicewire\\Beam\\Mcp\\Tests\\Fixtures\\FakeUnannotatedClass');
});

test('discover with a discover_path scans and groups exactly like the explicit-classes path', function () {
    $manifest = new McpToolManifest;

    $manifest->discover(paths: [__DIR__.'/../Fixtures']);

    expect($manifest->classesFor('shop'))->toEqualCanonicalizing([FakeShopToolOne::class, FakeShopToolTwo::class])
        ->and($manifest->classesFor('retrieve'))->toBe([FakeRetrieveTool::class]);
});

test('scanPaths against a non-existent path returns empty rather than erroring', function () {
    $manifest = new McpToolManifest;

    expect($manifest->scanPaths([__DIR__.'/NoSuchDirectory']))->toBe([]);
});
