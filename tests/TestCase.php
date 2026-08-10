<?php

namespace Splicewire\Beam\Mcp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Beam\Manifest\ManifestIndex;
use Splicewire\Beam\Mcp\BeamMcpServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * `McpToolManifest`'s only real dependency on beam-core is {@see ManifestIndex} (a plain,
     * dependency-free in-memory accumulator) — booting the FULL `Splicewire\Beam\BeamServiceProvider`
     * just to get that singleton would pull in media-library/activitylog/laravel-data test weight
     * this package's registry never touches. `ManifestIndex::class` is bound directly here, before
     * any provider registers, exactly as `BeamServiceProvider::register()` binds it in the real app.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        $app->singleton(ManifestIndex::class);

        return [
            BeamMcpServiceProvider::class,
        ];
    }
}
