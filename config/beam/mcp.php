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

];
