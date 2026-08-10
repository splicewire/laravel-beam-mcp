<?php

use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeRetrieveTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolOne;

test('boot reads beam.mcp.classes and discovers into the container singleton', function () {
    $manifest = app(McpToolManifest::class);

    expect($manifest->classesFor('shop'))->toBe([FakeShopToolOne::class])
        ->and($manifest->classesFor('retrieve'))->toBe([FakeRetrieveTool::class]);
});
