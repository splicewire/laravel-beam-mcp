<?php

namespace Splicewire\Beam\Mcp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder;
use Splicewire\Beam\Mcp\Http\Controllers\McpManifestController;
use Splicewire\Beam\Mcp\Surgeon\McpToolShapeAudit;
use Splicewire\Beam\Seed\BeamSeedManifest;

/**
 * The MCP-arm provider: any beam-tier package exposes an MCP tool by annotating the class
 * `#[McpTool('group')]`, no central host-side edit required (see {@see McpToolManifest}).
 *
 * register(): merge config, bind the manifest singleton (a plain in-memory accumulator — safe as
 * a container singleton, no external state).
 *
 * boot(): publish config; run `#[McpTool]` discovery from `beam.mcp.classes` /
 * `.discover_paths` (absent config ⇒ no-op, exactly as beam-core's `#[ParticleResource]`
 * discovery does); describe the manifest into beam-core's {@see ManifestIndex}.
 */
class BeamMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/beam/mcp.php', 'beam.mcp');

        $this->app->singleton(McpToolManifest::class);
    }

    public function boot(): void
    {
        // DOCS-06b: this package's docs page templates (current and the prior one found on live rows) for beam-ux's
        // provenance backfill. By class-string, as everywhere in this package: beam-ux may not be installed.
        $templates = 'Splicewire\\Beam\\Ux\\Provenance\\ProvenanceTemplates';
        if (class_exists($templates) && $this->app->bound($templates)) {
            $this->app->make($templates)->register(fn (): array => array_map(
                fn (array $t): object => new ('Splicewire\\Beam\\Ux\\Provenance\\ProvenanceTemplate')(
                    \Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder::ORIGIN, null, 'docs-mcp', $t['template'], $t['label'],
                ),
                \Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder::provenanceTemplates(),
            ));
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/beam/mcp.php' => $this->app->configPath('beam/mcp.php'),
            ], 'beam-mcp-config');

            // The docs page stub. Optionally published — publishing is how a host rewords what a fresh
            // install seeds; not publishing is how it gets the default. A stub is not a rendered page,
            // which is the thing no beam package ships.
            $this->publishes([
                __DIR__.'/../stubs/docs' => resource_path('beam-mcp/docs'),
            ], 'beam-mcp-docs');
        }

        $this->mountManifestRoute();
        $this->registerDocsSeed();

        $this->discoverMcpTools();
        $this->describeToolManifest();
        $this->registerDoctorAudits();
    }

    /**
     * Registry-kernel ticket 38: declaring and indexing are two acts (ticket 21 D1), and until the
     * second one lands the index holds nothing. Described from this package's own `boot()`, AFTER
     * `discoverMcpTools()` has filled it, `by:` this provider.
     */
    protected function describeToolManifest(): void {}

    /**
     * Mount the advertised-catalog endpoint (ADR-0210 §3). The package mounts this ITSELF, unlike the
     * beam-ux renderer which is host-mounted: ADR-0116 guards against a package claiming UNMATCHED urls,
     * not against one owning a fixed, namespaced, read-only route of its own. Requiring a host to
     * hand-mount one route per installed contributor would defeat the install-and-it-appears property
     * the contribution shape exists for.
     *
     * No middleware: the advertised catalog is the buyer-facing superset, publicly cacheable by design
     * (§4). A host that wants it behind auth sets `manifest_uri` to null and mounts its own.
     */
    protected function mountManifestRoute(): void
    {
        $uri = config('beam.mcp.manifest_uri', 'beam/mcp/manifest.json');

        if ($uri === null || $uri === '') {
            return;
        }

        Route::get($uri, McpManifestController::class)->name('beam.mcp.manifest');
    }

    /**
     * Register beam-mcp's ONE seed step DOWN into beam-core's manifest — the whole of what ADR-0210 §1
     * means by a contribution registering itself. Order 30, after beam-ux's docs root at 20, because the
     * page hangs off it.
     *
     * Registered UNCONDITIONALLY and guarded inside the seeder instead: the manifest takes a
     * class-string, so nothing loads until the seeder runs, and a headless host gets a reported skip
     * rather than a missing-class fatal (§6). Guarded here only on the manifest existing, so this
     * package still boots against a beam-core that predates it.
     */
    protected function registerDocsSeed(): void
    {
        if (! class_exists(BeamSeedManifest::class) || ! $this->app->bound(BeamSeedManifest::class)) {
            return;
        }

        $this->app->make(BeamSeedManifest::class)->register(
            package: 'splicewire/laravel-beam-mcp',
            seederClass: McpDocsSeeder::class,
            order: 30,
        );
    }

    /**
     * Register the MCP leg of the negative-space detector DOWN into beam-core's doctor manifest, from this
     * package's own provider — the acyclic direction beam-core relies on (it iterates whatever registered and
     * never learns a consumer's name).
     *
     * Advisory, matching the HTTP leg: the undeclared MCP surface is a burn-down, and a backlog that fails the
     * build is just a blocked build.
     *
     * Guarded on the manifest class existing so this package still boots against a beam-core that predates it.
     */
    protected function registerDoctorAudits(): void
    {
        if (! class_exists(BeamDoctorManifest::class)) {
            return;
        }

        $this->app->bind(McpToolShapeAudit::class, fn ($app) => new McpToolShapeAudit(
            $app->make(McpToolManifest::class),
        ));

        $this->app->make(BeamDoctorManifest::class)->register(
            'splicewire/laravel-beam-mcp',
            McpToolShapeAudit::class,
        );
    }

    /**
     * Boot-time `#[McpTool]` discovery into the singleton {@see McpToolManifest}. Reads
     * `beam.mcp.classes` / `.discover_paths`. Absent config ⇒ no-op — a host that has not opted
     * any package into the manifest yet boots unchanged.
     */
    protected function discoverMcpTools(): void
    {
        $classes = config('beam.mcp.classes', []);
        $paths = config('beam.mcp.discover_paths', []);

        if ($classes === [] && $paths === []) {
            return;
        }

        $this->app->make(McpToolManifest::class)->discover($classes, $paths);
    }
}
