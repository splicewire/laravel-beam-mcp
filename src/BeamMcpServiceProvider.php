<?php

namespace Splicewire\Beam\Mcp;

use Illuminate\Support\ServiceProvider;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Manifest\ManifestArity;
use Splicewire\Beam\Manifest\ManifestDescriptor;
use Splicewire\Beam\Manifest\ManifestIndex;
use Splicewire\Beam\Manifest\ManifestSeam;
use Splicewire\Beam\Mcp\Surgeon\McpToolShapeAudit;

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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/beam/mcp.php' => $this->app->configPath('beam/mcp.php'),
            ], 'beam-mcp-config');
        }

        $this->discoverMcpTools();
        $this->describeMcpManifest();
        $this->registerDoctorAudits();
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

    /**
     * Describe {@see McpToolManifest} into the index of indexes (beam-manifest-index). Owner
     * self-registration, down into beam-core's {@see ManifestIndex} — same direction as the
     * install/doctor manifests, topology-safe (beam-mcp depends DOWN on laravel-beam).
     *
     * Arity is RUN-ALL, not pick-one like the other attribute-scan registries
     * (AdminResourceRegistry/ParticleOperationRegistry/RealmRegistry all resolve one entry by
     * key): a consuming MCP server wants EVERY registered group at `groups()` time, not one
     * group by name — the read shape genuinely differs, so the label does too.
     */
    protected function describeMcpManifest(): void
    {
        $this->app->make(ManifestIndex::class)->describe(new ManifestDescriptor(
            name: 'McpToolManifest',
            of: 'MCP tool classes grouped by mount group, exposed to a host MCP server\'s groups()',
            seam: ManifestSeam::AttributeScan,
            arity: ManifestArity::RunAll,
            registerHint: 'annotate a Tool class #[McpTool(\'group\')] (or add its dir to beam.mcp.discover_paths)',
            where: '#[McpTool] → '.McpToolManifest::class,
            package: 'splicewire/laravel-beam-mcp',
            order: 16,
        ));
    }
}
