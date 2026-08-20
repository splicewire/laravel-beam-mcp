<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Explicit tool classes
    |--------------------------------------------------------------------------
    |
    | #[McpTool]-annotated class-strings registered directly, no filesystem
    | walk — cheap, always honoured alongside discover_paths.
    |
    */
    'classes' => [],

    /*
    |--------------------------------------------------------------------------
    | Discover paths
    |--------------------------------------------------------------------------
    |
    | Filesystem paths scanned for #[McpTool]-annotated classes at boot. A
    | beam-tier package (or the host app) adds its own Mcp/Tools directory
    | here to expose a tool without a central host-side edit. Absent
    | classes/discover_paths ⇒ the manifest boots empty (no-op), exactly as
    | beam-core's #[ParticleResource] discovery does with no config.
    |
    */
    'discover_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Advertised-catalog endpoint (ADR-0210 §3)
    |--------------------------------------------------------------------------
    |
    | The fixed, namespaced, read-only JSON route this package mounts ITSELF so
    | an installed contributor's docs page just works. This is not the concern
    | ADR-0116 guards against — that is a package silently claiming UNMATCHED
    | urls, which is why the beam-ux renderer is host-mounted. Requiring a host
    | to hand-mount one route per installed contributor would defeat the
    | install-and-it-appears property the whole shape exists for.
    |
    | Set to null to mount nothing (a host that serves its own catalog).
    |
    */
    'manifest_uri' => env('BEAM_MCP_MANIFEST_URI', 'beam/mcp/manifest.json'),

    /*
    |--------------------------------------------------------------------------
    | Docs page seed (ADR-0210 §1)
    |--------------------------------------------------------------------------
    |
    | beam-mcp's docs contribution is ONE seed row under beam-ux's docs root.
    | The step registers unconditionally and no-ops when beam-ux is absent — an
    | MCP server with no UX package is a legitimate install, and a headless host
    | running `splicewire:beam:seed` gets a reported skip, not a crash.
    |
    */
    'docs' => [
        'seed' => env('BEAM_MCP_SEED_DOCS', true),
    ],

];
