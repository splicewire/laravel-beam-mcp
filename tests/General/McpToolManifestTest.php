<?php

use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeRetrieveTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolOne;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolTwo;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeUnannotatedClass;

test('registerClass groups an annotated class by its declared #[McpTool] group', function () {
    $manifest = new McpToolManifest;

    $manifest->registerClass(FakeShopToolOne::class);

    expect($manifest->has('shop'))->toBeTrue()
        ->and($manifest->classesFor('shop'))->toBe([FakeShopToolOne::class])
        ->and($manifest->has('retrieve'))->toBeFalse();
});

test('registerClass accumulates multiple classes into the same group', function () {
    $manifest = new McpToolManifest;

    $manifest->registerClass(FakeShopToolOne::class);
    $manifest->registerClass(FakeShopToolTwo::class);

    expect($manifest->classesFor('shop'))->toBe([FakeShopToolOne::class, FakeShopToolTwo::class]);
});

test('registerClass is idempotent — re-registering the same class never duplicates', function () {
    $manifest = new McpToolManifest;

    $manifest->registerClass(FakeShopToolOne::class);
    $manifest->registerClass(FakeShopToolOne::class);

    expect($manifest->classesFor('shop'))->toBe([FakeShopToolOne::class]);
});

test('registerClass throws for a class with no #[McpTool] attribute', function () {
    $manifest = new McpToolManifest;

    $manifest->registerClass(FakeUnannotatedClass::class);
})->throws(InvalidArgumentException::class, 'is not annotated with #[McpTool]');

test('registerClass throws for a non-existent class', function () {
    $manifest = new McpToolManifest;

    $manifest->registerClass('App\\Definitely\\Not\\A\\Real\\Class');
})->throws(InvalidArgumentException::class, 'does not exist');

test('discover registers an explicit class list with no filesystem scan', function () {
    $manifest = new McpToolManifest;

    $manifest->discover(classes: [FakeShopToolOne::class, FakeRetrieveTool::class]);

    expect($manifest->grouped())->toBe([
        'shop' => [FakeShopToolOne::class],
        'retrieve' => [FakeRetrieveTool::class],
    ]);
});

test('toolGroups projects the grouped registry into Rushing\\McpRegistry\\ToolGroup objects', function () {
    $manifest = new McpToolManifest;
    $manifest->discover(classes: [FakeShopToolOne::class, FakeShopToolTwo::class, FakeRetrieveTool::class]);

    $groups = $manifest->toolGroups();

    expect($groups)->toHaveCount(2);

    $byName = collect($groups)->keyBy(fn ($g) => $g->name());
    expect($byName['shop']->tools())->toBe([FakeShopToolOne::class, FakeShopToolTwo::class])
        ->and($byName['retrieve']->tools())->toBe([FakeRetrieveTool::class]);
});

test('all returns every registered class across every group, de-duplicated', function () {
    $manifest = new McpToolManifest;
    $manifest->discover(classes: [FakeShopToolOne::class, FakeShopToolTwo::class, FakeRetrieveTool::class]);

    expect($manifest->all())->toBe([FakeShopToolOne::class, FakeShopToolTwo::class, FakeRetrieveTool::class]);
});

test('an empty manifest reads back empty, not an error', function () {
    $manifest = new McpToolManifest;

    expect($manifest->grouped())->toBe([])
        ->and($manifest->toolGroups())->toBe([])
        ->and($manifest->all())->toBe([])
        ->and($manifest->has('shop'))->toBeFalse()
        ->and($manifest->classesFor('shop'))->toBe([]);
});
