> You are in **rushing/laravel-beam-mcp** — the MCP arm of the schemastud beam family.

Any beam-tier package exposes an MCP tool by annotating the tool class `#[McpTool('group')]` — no
Tower-side edit required. Ships the attribute-scan `McpToolManifest` registry (grouped tool-class
discovery, self-described into beam-core's `ManifestIndex`) that a host MCP server (e.g.
`Splicewire\Tower\Mcp\Servers\SplicewireServer`) composes at boot, replacing a hand-maintained
tool-class list.

## Particle doctrine

Before adding or changing any I/O surface (HTTP route, MCP tool, Inertia page, command), read
`~/Workspaces/splicewire-beam-runbook/references/particle-doctrine.md` — the
declare-every-boundary-crossing-shape invariant, its three declaration sites, the four exceptions,
and `splicewire:beam:manifests --json` for locating the registry behind a surface.

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
