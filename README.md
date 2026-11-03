# splicewire/laravel-beam-mcp

The MCP arm of the schemastud beam family: any beam-tier package exposes an MCP tool by
annotating the tool class `#[McpTool('group')]` — no Tower-side edit required.

## The seam

A host MCP server doesn't hand-list the tool classes it exposes or reach into other packages'
`Mcp\*` namespaces. A package registers its own tools, and the server
(e.g. `Splicewire\Tower\Mcp\Servers\SplicewireServer`) reads the groups from the manifest.

```php
use Laravel\Mcp\Server\Tool;
use Splicewire\Beam\Mcp\Attributes\McpTool;

#[McpTool('shop')]
class CreateCheckoutTool extends Tool
{
    // ...
}
```

Add the class (or its directory) to `beam.mcp.classes` / `beam.mcp.discover_paths`
(`config/beam/mcp.php`), and it's discovered at boot into the `McpToolManifest` singleton. A
package can also call `app(McpToolManifest::class)->discover(classes: [...])` from its own
provider's `boot()`. The manifest is the `beam.mcp.tools` registry, so `popcorn:registries` lists it.

## Registries

This package declares one registry:

- [`beam.mcp.tools`](docs/registries/beam.mcp.tools.mdx): the `#[McpTool]` classes a host's MCP
  server mounts, grouped by mount group.

It registers into three registries `splicewire/laravel-beam` owns:

- [`beam.install.steps`](https://github.com/splicewire/laravel-beam/blob/main/docs/registries/beam.install.steps.mdx):
  the install step that publishes the MCP config and the optional docs stubs.
- [`beam.doctor.audits`](https://github.com/splicewire/laravel-beam/blob/main/docs/registries/beam.doctor.audits.mdx):
  the advisory MCP tool shape audit.
- [`beam.seed.steps`](https://github.com/splicewire/laravel-beam/blob/main/docs/registries/beam.seed.steps.mdx):
  the MCP docs seeder.

## Consuming the manifest

```php
use Splicewire\Beam\Mcp\McpToolManifest;

// Rushing\McpRegistry\ToolGroup[] — drop straight into a Server's groups() method.
app(McpToolManifest::class)->toolGroups();

// Or the flat, de-duplicated read across every group.
app(McpToolManifest::class)->all();
```

## Local dev

```bash
composer install
composer test   # pest
composer pint   # style
```
