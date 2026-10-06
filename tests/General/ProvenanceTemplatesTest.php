<?php

use Splicewire\Beam\Mcp\Database\Seeders\McpDocsSeeder;

/**
 * DOCS-06b: the MCP page's live rows were seeded from the stub as it stood at laravel-beam-mcp@e7a3da9 (its
 * `{{ endpoint_url }}` printed the dead `<app.url>/mcp` on www and beam.test). The package ships that one prior template
 * beside the current stub, so beam-ux's provenance backfill can recognise those rows as this package's and the next seed
 * re-asserts them (the integration is proven at a host that installs both, splicewire.test).
 */
test('the MCP page templates are the current stub and the prior one found on live rows', function () {
    @unlink(resource_path('beam-mcp/docs/mcp.mdx'));
    $templates = McpDocsSeeder::provenanceTemplates();

    expect(array_column($templates, 'label'))->toBe(['beam-mcp mcp', 'beam-mcp mcp@e7a3da9'])
        ->and($templates[0]['template'])->toContain('{{ mcp_servers }}')
        ->and($templates[1]['template'])->toContain('{{ endpoint_url }}')
        ->and(McpDocsSeeder::ORIGIN)->toBe('package:splicewire/laravel-beam-mcp');
});
