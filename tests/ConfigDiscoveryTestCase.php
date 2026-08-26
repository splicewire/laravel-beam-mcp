<?php

namespace Splicewire\Beam\Mcp\Tests;

use Splicewire\Beam\Mcp\Tests\Fixtures\FakeRetrieveTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeShopToolOne;

/**
 * A TestCase variant that pre-seeds `beam.mcp.classes` before the app boots, so
 * `BeamMcpServiceProvider::discoverMcpTools()` has real config to read — proving the config seam
 * end-to-end (config → boot → registered in the container singleton), not just the underlying
 * `McpToolManifest::discover()` call in isolation (already unit-tested directly).
 */
abstract class ConfigDiscoveryTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        // The base case's environment carries the laravel-data config the host runs (ticket 85);
        // overriding without this call would silently drop it for every ConfigDriven/ test.
        parent::defineEnvironment($app);

        $app['config']->set('beam.mcp.classes', [
            FakeShopToolOne::class,
            FakeRetrieveTool::class,
        ]);
    }
}
