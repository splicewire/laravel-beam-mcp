<?php

/**
 * docs-walkthrough DOC-10, DOCS-11: no author note or provenance prose reaches a reader. The MCP stub becomes a page on
 * every host that seeds it, and it closed by telling readers "This page belongs to the host… package seeding leaves
 * existing pages unchanged" and opened in the package author's voice ("Installing `splicewire/laravel-beam-mcp`
 * provides…", "read from its route table when this page was seeded"). Provenance lives in the row's columns (DM2), the
 * doctor and the authoring ribbon.
 */
test('the MCP docs stub carries no author or provenance prose', function () {
    $phrases = ['Seeded by', 'Nothing re-asserts', 'BeamUxEntry', 'row this site owns', 'This site documents itself',
        'package seeding', 'This page belongs to the host', 'when this page was seeded', 'Installing `splicewire/', 'docs stub'];
    $body = (string) file_get_contents(dirname(__DIR__, 2).'/stubs/docs/mcp.mdx');

    expect(array_values(array_filter($phrases, fn (string $phrase): bool => str_contains($body, $phrase))))->toBe([]);
});
