> You are in **rushing/laravel-beam-mcp** — the MCP arm of the schemastud beam family.

Any beam-tier package exposes an MCP tool by annotating the tool class `#[McpTool('group')]` — no
Tower-side edit required. Ships the attribute-scan `McpToolManifest` registry (grouped tool-class
discovery, self-described into beam-core's `ManifestIndex`) that a host MCP server (e.g.
`Splicewire\Tower\Mcp\Servers\SplicewireServer`) composes at boot, replacing a hand-maintained
tool-class list.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
