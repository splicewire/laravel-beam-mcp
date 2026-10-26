<?php

namespace Splicewire\Beam\Mcp\Tests\Integration;

use Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder;

/**
 * EP-42 S1 (ADR-0233 d9, "a package runs it over the pages it ships"): on a host whose MDX pages hold blocks, this
 * package's docs page is seeded converted, the way laravel-beam-ux seeds its own (its ShippedMdx), so `docs-mcp` is
 * stored as blocks, placing <ManifestTable endpoint="…" />, and the host's MDX pages audit passes for it.
 */
class McpDocsSeederHoldsBlocksTest extends HoldsBlocksTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $ux = dirname((new \ReflectionClass(\Splicewire\Beam\Ux\BeamUxServiceProvider::class))->getFileName(), 2);
        (require $ux.'/database/migrations/shared/add_provenance_to_beam_ux_entries_table.php.stub')->up();
        \Illuminate\Support\Facades\Gate::define('create', fn ($user = null) => true);
        \Illuminate\Support\Facades\Gate::define('update', fn ($user = null) => true);
        $this->app->instance(\Splicewire\Beam\Mdx\Lens\MdxLens::class, new McpPageLens);
        config(['beam.ux.mdx.holds_blocks' => true]);
        $this->app->forgetInstance(\Splicewire\Beam\Ux\Codec\CodecRegistry::class);
        \Splicewire\Beam\Ux\Models\BeamUxEntry::create(['slug' => 'docs', 'title' => 'Docs', 'type' => 'page', 'format' => 'tsx', 'realm' => 'site',
            'parent_id' => \Splicewire\Beam\Ux\Models\BeamUxEntry::rootFor()->id, 'segment' => 'docs']);
    }

    public function test_the_mcp_docs_page_is_seeded_as_blocks_and_the_mdx_pages_audit_passes(): void
    {
        (new McpDocsSeeder)->run();

        $page = \Splicewire\Beam\Ux\Models\BeamUxEntry::query()->where('slug', 'docs-mcp')->firstOrFail();
        $body = app(\Rushing\Versioning\Contracts\VersionStore::class)->version($page->particle, $page->published_version)->snapshot['payload'];
        $this->assertTrue(array_is_list($body), 'stored as blocks');
        $table = collect($body)->firstWhere('name', 'ManifestTable');
        $this->assertNotNull($table, 'it places the ManifestTable');
        $this->assertSame('endpoint', $table['props'][0]['name']);
        $this->assertSame(McpDocsSeeder::ORIGIN, $page->origin);

        $findings = (new \Splicewire\Beam\Ux\Doctor\BeamUxMdxPagesAudit)->run();
        $this->assertNotContains('fail', array_map(fn ($finding) => $finding->status->value, $findings), 'no MDX page still holds source');
    }
}

/**
 * The MDX Lens for this page: its heading and prose as paragraphs and its <ManifestTable endpoint="…" /> as a component,
 * and whatever it prints reads back exactly (the conversion's safety check compares the two).
 */
final class McpPageLens implements \Splicewire\Beam\Mdx\Lens\MdxLens
{
    /** @var array<string, array> */
    private array $printed = [];

    public function read(string $body, array $context = []): array
    {
        if (isset($this->printed[trim($body)])) {
            return ['doc' => $this->printed[trim($body)], 'meta' => null, 'metaData' => null];
        }
        $doc = [];
        foreach (preg_split('/\n\s*\n/', trim($body)) as $chunk) {
            if (preg_match('/^<ManifestTable endpoint="([^"]*)" \/>$/', trim($chunk), $m) === 1) {
                $doc[] = ['kind' => 'block', 'name' => 'ManifestTable', 'isComponent' => true, 'props' => [['name' => 'endpoint', 'kind' => 'string', 'value' => $m[1]]], 'children' => [], 'dynamic' => false];
            } else {
                $doc[] = ['kind' => 'block', 'name' => 'p', 'isComponent' => false, 'props' => [], 'children' => [['kind' => 'text', 'value' => trim($chunk)]], 'dynamic' => false];
            }
        }

        return ['doc' => $doc, 'meta' => null, 'metaData' => null];
    }

    public function print(array $doc): string
    {
        $printed = 'PRINTED '.md5(json_encode($doc))."\n";
        $this->printed[trim($printed)] = $doc;

        return $printed;
    }
}
