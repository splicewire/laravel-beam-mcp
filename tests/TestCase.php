<?php

namespace Splicewire\Beam\Mcp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
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

            // Same omission, same mechanism, different library: testbench does not auto-discover, so
            // a provider this package never NAMES is a provider this package never BOOTS — while
            // `src/` autoloads `Spatie\LaravelData\*` freely. `Shapes/DataInputSchema` reads
            // spatie's `MapInputName` off consumer Data classes, and with the provider absent
            // `config('data')` is NULL inside this suite (measured true here before this line), so
            // anything that touches laravel-data's own machinery FATALS rather than fails.
            // api-surface-coherence tickets 84 / 85.
            LaravelDataServiceProvider::class,

            BeamMcpServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Booting LaravelDataServiceProvider alone would be a FALSE GREEN. The package ships
        // `name_mapping_strategy.input => null`; the only host that runs this code
        // (`~/Herd/splicewire-app/config/data.php`) sets it to CamelCaseMapper — the ONE semantic
        // delta between the two files. That is especially load-bearing HERE: `DataInputSchema` is
        // about input NAME MAPPING, exactly what this key governs, so a harness left on the package
        // default would be exercising a different mapping regime than the host actually runs.
        $app['config']->set('data.name_mapping_strategy.input', CamelCaseMapper::class);

        // Structure caching points at `app_path('Data')` by default and at a vendored path in the
        // host; neither exists here, and a reflection analysis cached across runs is exactly what a
        // harness should not carry.
        $app['config']->set('data.structure_caching.enabled', false);
    }
}
