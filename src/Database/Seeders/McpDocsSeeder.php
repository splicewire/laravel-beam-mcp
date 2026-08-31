<?php

namespace Splicewire\Beam\Mcp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Splicewire\Beam\Mdx\Frontmatter\FrontmatterParser;

/**
 * beam-mcp's docs contribution: **one seed row** (ADR-0210 §1). There is no registry to register into
 * and no page to ship — the row points `<ManifestTable>` at this package's own JSON endpoint, and the
 * component itself is generic and lives in `@splicewire/beam-ux/site`. beam-mcp ships zero frontend.
 *
 * **It takes no dependency on beam-ux, deliberately** (ADR-0210 §6 / Consequences). An MCP server with
 * no UX package is a legitimate install, so every reference to beam-ux here is a *string* resolved at
 * run time behind `class_exists`, not an import — the same reason `BeamSeedManifest` takes a
 * class-string: nothing loads until the seeder actually runs. On a headless host this is a reported
 * skip, never a missing-class fatal. The JSON endpoint still mounts; a headless MCP host serving its own
 * manifest is harmless and arguably useful.
 *
 * The trade that buys is duplication: this cannot use beam-ux's `SeedsEntries` trait or its
 * `StubContent` reader, so it carries its own twenty lines of both. That is the honest price of the
 * no-dependency rule, and it is cheaper than the alternative — beam-mcp requiring the whole authoring
 * engine to seed a page about itself.
 */
class McpDocsSeeder extends Seeder
{
    /** Resolved by string, never imported: beam-ux may not be installed. */
    private const ENTRY = 'Splicewire\Beam\Ux\Models\BeamUxEntry';

    private const DRIVERS = 'Splicewire\Beam\Ux\Storage\StorageDriverResolver';

    private const COMPILE = 'Splicewire\Beam\Ux\Compile\CompileEntryBody';

    public function run(): void
    {
        if (! config('beam.mcp.docs.seed', true)) {
            return;
        }

        if (! class_exists(self::ENTRY) || ! Schema::hasTable('beam_ux_entries')) {
            $this->report('beam-mcp: beam-ux is not installed — skipping the MCP docs page.');

            return;
        }

        $entry = self::ENTRY;

        // The docs root beam-ux seeded. Absent ⇒ nothing to contribute TO: a host that gated the docs
        // subtree off has said it does not want one, and materializing a stray top-level MCP page would
        // be a package overriding that.
        $parent = $entry::query()->where('slug', 'docs')->first();

        if ($parent === null) {
            $this->report('beam-mcp: no docs root — skipping the MCP docs page.');

            return;
        }

        if ($entry::query()->where('slug', 'docs-mcp')->exists()) {
            // Create, never update: the row is site-owned from creation (ADR-0210 §6), so a re-seed
            // after someone has re-worded or moved the page must leave every edit alone.
            return;
        }

        $stub = $this->stub();

        if ($stub === null) {
            return;
        }

        $this->create($entry, $parent, $stub);
    }

    /**
     * Create the page row, write its body through beam-ux's storage driver, and compile it so a fresh
     * host serves the page on first boot rather than 404ing until someone runs the backfill.
     *
     * @param  class-string  $entry
     * @param  array{columns: array<string, mixed>, body: string}  $stub
     */
    private function create(string $entry, object $parent, array $stub): void
    {
        // Transactional, and BORN PUBLISHED — the two properties beam-ux's `SeedsEntries` carries, kept
        // in step here by hand. This is the duplication the class docblock priced in: the no-beam-ux-
        // dependency rule means this seeder cannot use the trait, so a fix to seeding semantics has to
        // be made twice. Both were found on `splicewire/www` (beam-docs-satellite ticket 07):
        //
        //  - Without the transaction, a throw between the row and its body leaves a BODYLESS row, and
        //    the create-never-update check above then makes it permanent — the retry is a silent no-op.
        //  - Without the marking, `WorkflowMarkingPublishGate` 404s the page on every host that binds a
        //    `page` workflow, so a contributed page that resolves and compiles correctly is still
        //    invisible. `$stub['columns']` merges over this, so frontmatter can still say otherwise.
        $page = \Illuminate\Support\Facades\DB::transaction(function () use ($entry, $parent, $stub) {
            // The marking comes from beam-ux's own guarded helper (it is a no-op where the optional
            // `workflow_marking` column is absent), resolved off the class-string like everything else
            // here so this file still imports nothing from beam-ux.
            $page = $entry::create(array_merge([
                'slug' => 'docs-mcp',
                'type' => 'page',
                'format' => 'mdx',
                'parent_id' => $parent->getKey(),
            ], $entry::publishedMarkingAttributes(), $stub['columns']));

            $drivers = app(self::DRIVERS);
            $written = $drivers->resolve($page)->write('', $page->codec()->encode($stub['body']), $page->namespace);

            if ($written->key !== '') {
                $page->particle_id = $written->key;
                $page->save();
            }

            return $page;
        });

        try {
            app(self::COMPILE)->forEntry($page->refresh(), $stub['body'], force: true);
        } catch (\Throwable) {
            // Reported by beam-ux's artifact doctor check. A seed runs on hosts with no Node (CI, a
            // container build stage), and taking down `beam:seed` over it would be worse.
        }
    }

    /**
     * The page stub — the host's published copy if it has one, else this package's own. The endpoint URL
     * is interpolated because a seed body cannot call `route()`, and hardcoding it in the MDX would be a
     * second place the mount path is written down.
     *
     * @return array{columns: array<string, mixed>, body: string}|null
     */
    private function stub(): ?array
    {
        $published = resource_path('beam-mcp/docs/mcp.mdx');
        $path = is_file($published) ? $published : __DIR__.'/../../../stubs/docs/mcp.mdx';

        if (! is_file($path)) {
            return null;
        }

        $raw = strtr((string) file_get_contents($path), [
            '{{ manifest_url }}' => $this->manifestUrl(),
            '{{manifest_url}}' => $this->manifestUrl(),
            '{{ endpoint_url }}' => $this->endpointUrl(),
            '{{endpoint_url}}' => $this->endpointUrl(),
        ]);

        return ['columns' => $this->columns($raw), 'body' => $raw];
    }

    /**
     * The flat `key: value` frontmatter, as entry columns. Only set keys are returned, so an unresolved
     * field falls to the model default rather than being written as null.
     *
     * Reads through the shared {@see FrontmatterParser} (frontmatter-declaration-seam ticket 04) — the
     * fifth and last copy of the grammar, retired.
     *
     * ⚠️ Takes the CANONICAL fields, like beam-ux's two column-projecting readers and unlike `Mdx` and
     * `MdxBody`, because these three keys ARE entry columns and columns are snake by definition.
     *
     * That matters here even though this package writes its own stub, because {@see pageStub()} is
     * **published-copy-first**: a host may `vendor:publish` this file into `resources/beam-mcp/` and
     * edit it. A host writing `navOrder:` previously parsed cleanly and had the value dropped — the
     * same silent no-op this charter exists to remove, on a file a host is explicitly invited to own.
     *
     * @return array<string, mixed>
     */
    private function columns(string $raw): array
    {
        $fields = app(FrontmatterParser::class)->parse($raw)->fields;

        $out = [];

        foreach (['title', 'segment'] as $key) {
            if (($fields[$key] ?? '') !== '') {
                $out[$key] = $fields[$key];
            }
        }

        if (isset($fields['nav_order']) && is_numeric($fields['nav_order'])) {
            $out['nav_order'] = (int) $fields['nav_order'];
        }

        return $out;
    }

    /**
     * The MCP transport endpoint a client is told to point at.
     *
     * This package mounts the tool MANIFEST, never the transport — the server route is the host's, via
     * `laravel/mcp` — so there is no route name here to resolve and the honest answer is the
     * conventional mount on this host's own `app.url`. The page says as much in prose, and it is a row
     * the site owns, so a host that mounted elsewhere edits it once.
     *
     * It is derived rather than literal because the stub used to ship `https://example.test/mcp` to
     * every install — a page telling every reader to point their MCP client at a placeholder domain.
     * Same shape as ADR-0211 §6's hardcoded artifact path: a literal that is right nowhere.
     */
    private function endpointUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $path = '/'.ltrim((string) config('beam.mcp.endpoint_uri', 'mcp'), '/');

        return $base.$path;
    }

    private function manifestUrl(): string
    {
        return app('router')->has('beam.mcp.manifest')
            ? route('beam.mcp.manifest', absolute: false)
            : '/'.ltrim((string) config('beam.mcp.manifest_uri', 'beam/mcp/manifest.json'), '/');
    }

    private function report(string $message): void
    {
        $this->command?->getOutput()->writeln("  <comment>{$message}</comment>");
    }
}
