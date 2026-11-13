<?php

namespace Splicewire\Beam\Mcp\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Splicewire\Beam\Mcp\Routing\McpRoutes;

class PackageRootTest extends TestCase
{
    public function test_default_reuses_the_identical_legacy_manifest_and_override_twins_it(): void
    {
        $routes = app('router')->getRoutes();
        $before = count($routes);
        $old = $routes->match(Request::create('/beam/mcp/manifest.json'));
        McpRoutes::mount();
        McpRoutes::mount();
        $this->assertCount($before, $routes);
        config(['beam.mcp.api_root' => 'custom/tools']);
        McpRoutes::mount();
        McpRoutes::mount();
        $new = $routes->match(Request::create('/custom/tools/manifest.json'));
        $this->assertSame($old->getActionName(), $new->getActionName());
        $this->assertSame($old->gatherMiddleware(), $new->gatherMiddleware());
        $this->assertSame('beam.mcp.api.manifest', $new->getName());
        $this->assertCount($before + 1, $routes);
    }

    public function test_explicit_override_is_independent_of_the_legacy_provider_switch(): void
    {
        config(['beam.mcp.manifest_uri' => null, 'beam.mcp.api_root' => 'disabled/tools']);
        McpRoutes::mount();
        $this->assertTrue(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($r) => $r->uri() === 'disabled/tools/manifest.json'));
    }

    public function test_cached_default_exposure_is_reused_and_different_middleware_refuses(): void
    {
        $router = app('router');
        $router->setCompiledRoutes($router->getRoutes()->compile());
        $count = count($router->getRoutes());
        McpRoutes::mount();
        $this->assertCount($count, $router->getRoutes());
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('beam.mcp.manifest');
        Route::middleware('host-private')->group(fn () => McpRoutes::mount());
    }

    public function test_reuse_does_not_freeze_later_host_middleware(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/beam/mcp/manifest.json'));
        McpRoutes::mount();
        $route->middleware('after-mcp-mount');
        $this->assertContains('after-mcp-mount', $route->gatherMiddleware());
    }
}
