<?php

use Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder;

/**
 * frontmatter-declaration-seam 04e — the fifth and last hand-rolled frontmatter reader, retired.
 *
 * This one looked like it did not need the shared grammar, because the package writes its own stub.
 * It does: `stub()` is **published-copy-first** (`resource_path('beam-mcp/docs/mcp.mdx')` before
 * the packaged file), so a host may publish and edit it. A host writing `navOrder:` parsed cleanly
 * and had the value silently dropped — the charter's defect, on a file a host is explicitly invited
 * to own.
 */
function seedStub(string $frontmatter): array
{
    $dir = resource_path('beam-mcp/docs');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/mcp.mdx', "---\n{$frontmatter}\n---\n# MCP\n\nBody.\n");

    $seeder = new McpDocsSeeder;
    $method = new ReflectionMethod($seeder, 'stub');

    return $method->invoke($seeder);
}

test('a host-published stub authored in camelCase lands in its snake column', function () {
    $stub = seedStub("title: MCP\nsegment: /mcp\nnavOrder: 5");

    // The assertion that could not have passed before the collapse.
    expect($stub['columns']['nav_order'])->toBe(5)
        ->and($stub['columns']['title'])->toBe('MCP')
        ->and($stub['columns']['segment'])->toBe('/mcp');
});

test('a snake-authored stub is unchanged', function () {
    expect(seedStub("title: MCP\nnav_order: 6")['columns']['nav_order'])->toBe(6);
});

test('the body keeps the authored source, frontmatter included', function () {
    // Canonicalization touches the COLUMN projection only — the body is what the entry stores.
    expect(seedStub('title: MCP')['body'])->toContain('title: MCP');
});
