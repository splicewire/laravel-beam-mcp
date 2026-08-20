<?php

namespace Splicewire\Beam\Mcp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

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
        $page = $entry::create(array_merge([
            'slug' => 'docs-mcp',
            'type' => 'page',
            'format' => 'mdx',
            'parent_id' => $parent->getKey(),
        ], $stub['columns']));

        $drivers = app(self::DRIVERS);
        $written = $drivers->resolve($page)->write('', $page->codec()->encode($stub['body']), $page->namespace);

        if ($written->key !== '') {
            $page->particle_id = $written->key;
            $page->save();
        }

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

        $raw = str_replace(
            ['{{ manifest_url }}', '{{manifest_url}}'],
            $this->manifestUrl(),
            (string) file_get_contents($path),
        );

        return ['columns' => $this->columns($raw), 'body' => $raw];
    }

    /**
     * The flat `key: value` frontmatter, as entry columns. Only set keys are returned, so an unresolved
     * field falls to the model default rather than being written as null.
     *
     * @return array<string, mixed>
     */
    private function columns(string $raw): array
    {
        if (! preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?/s', $raw, $match)) {
            return [];
        }

        $out = [];

        foreach (preg_split('/\r?\n/', $match[1]) as $line) {
            if (! preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $kv)) {
                continue;
            }

            $key = $kv[1];
            $value = trim($kv[2], " \t\"'");

            if (in_array($key, ['title', 'segment'], true) && $value !== '') {
                $out[$key] = $value;
            }

            if ($key === 'nav_order' && is_numeric($value)) {
                $out['nav_order'] = (int) $value;
            }
        }

        return $out;
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
