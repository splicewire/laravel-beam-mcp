<?php

namespace Splicewire\Beam\Mcp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
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
        // Same reasoning for the doctor manifest: beam-core binds it as a SINGLETON, and this package's
        // provider registers its audit into it at boot. Without the singleton here, that registration would
        // land on a throwaway instance and be invisible to any assertion.
        $app->singleton(BeamDoctorManifest::class);

        return [
            BeamMcpServiceProvider::class,
        ];
    }
}
