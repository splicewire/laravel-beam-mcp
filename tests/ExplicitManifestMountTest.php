<?php

namespace Splicewire\Beam\Mcp\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Mcp\Routing\McpRoutes;

class ExplicitManifestMountTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('beam.mcp.manifest_uri', null);
    }

    public function test_a_disabled_legacy_provider_allows_an_explicit_protected_default_root(): void
    {
        $routes = app('router')->getRoutes();
        $this->assertFalse(collect($routes->getRoutes())->contains(fn ($route) => $route->uri() === 'beam/mcp/manifest.json'));
        Route::middleware('auth')->group(fn () => McpRoutes::mount());
        $route = $routes->match(Request::create('/beam/mcp/manifest.json'));
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertSame('beam.mcp.api.manifest', $route->getName());
        $routes->refreshNameLookups();
        $this->assertNull($routes->getByName('beam.mcp.manifest'));
    }
}
