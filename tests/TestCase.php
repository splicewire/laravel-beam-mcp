<?php

namespace Splicewire\Beam\Mcp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Mcp\BeamMcpServiceProvider;
use Splicewire\Beam\Seed\BeamSeedManifest;

abstract class TestCase extends Orchestra
{
    /**
     * beam-core's doctor and seed manifests are bound directly rather than by booting the FULL
     * `Splicewire\Beam\BeamServiceProvider`, which would pull in media-library/activitylog/laravel-data
     * test weight this package's registry never touches. Each is bound as a SINGLETON exactly as
     * `BeamServiceProvider::register()` binds it in the real app, because this package's provider
     * registers into both at boot and a throwaway instance would make those registrations invisible.
     *
     * There is no index binding here any more: registry-kernel ticket 21 moved self-description onto
     * the class as `#[IsRegistry]`, so being listed no longer requires reaching into beam-core at all.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        // beam-core binds the doctor manifest as a SINGLETON, and this package's
        // provider registers its audit into it at boot. Without the singleton here, that registration would
        // land on a throwaway instance and be invisible to any assertion.
        $app->singleton(BeamDoctorManifest::class);
        // And the seed manifest: this package registers its ONE docs seed step (ADR-0210 §1) into it at
        // boot, and without the singleton that registration would land on a throwaway instance.
        $app->singleton(BeamSeedManifest::class);

        return [
            // Registry-kernel ticket 27 D3 / sweep-brief §3b finding 2: testbench does NOT
            // auto-discover, so without this provider `RegistryIndex` resolves to a fresh throwaway
            // per `make()` and every `describe()` lands on an object nobody can read back — a suite
            // that stays green over an empty index. `IndexIsSharedTest` is the tripwire.
            PopcornServiceProvider::class,
            BeamMcpServiceProvider::class,
        ];
    }
}
