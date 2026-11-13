# MCP advertised catalog root

Call `McpRoutes::mount()` from the host's route file or a booted callback, after package providers. It explicitly serves `manifest.json` under `beam.mcp.api_root` (default `beam/mcp`). The host owns its boundary; no new provider auto-mount is added. The MCP protocol endpoint is a distinct host/realm surface.

During expansion:

- With the default public legacy provider enabled, a bare call at the same URI reuses that route and its old name. A different middleware group at the same URI refuses by name.
- A root override adds a twin while the legacy public URL still answers.
- Set the legacy `manifest_uri` to null before provider boot to disable that provider route. An explicit authenticated mount still works at the canonical root; the legacy switch controls only the provider.

The reuse check reads declared middleware without caching it. Later host middleware appends still apply, including when reusing a cached collection. New twin names use the one `beam.mcp.api.*` constant; the contract step restores `beam.mcp.*` after legacy removal. Same-URI reuse keeps the first name.

The host and generated clients adopt the root separately. `Database/Seeders/McpDocsSeeder.php` and the tools registry doc still consume the legacy name/path and must migrate before legacy removal. ApiRoot is supplied by Beam ADR-0243, available from c583da31; the landing owner resolves that dependency.
