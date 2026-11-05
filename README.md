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

## Generating a tool

laravel/mcp's `make:mcp-tool` emits a tool with a hand-written `schema()` and no group or ability, so once
listed it is two `particle.undeclared-mcp-shape` findings. This package's generator emits one that starts
declared:

```bash
php artisan splicewire:beam:mcp:make:tool ListWidgetsTool --ability=widgets.read
```

That writes `App\Mcp\Tools\ListWidgetsTool` with `#[McpTool('list-widgets')]`, `#[ToolAbility('widgets.read')]`,
`DerivesInputSchemaFromData` and an `outputSchema()`, plus `App\Data\ListWidgetsInputData`, the class its input
schema is derived from (kept if it already exists).

- `--ability` is required. It names your host's ability vocabulary, so there is no safe default.
- `--group` defaults to the kebab-cased name without its `Tool` suffix; `--data-namespace` moves the input class.
- The command prints the `beam.mcp.classes` line to add; it does not edit your config.
- It then checks what it wrote: the files lint, and the tool, discovered into the manifest, has no shape finding
  and carries the requested group and ability. A finding exits 1; `--no-verify` skips the check.
- To change what it emits, put your own copy of `stubs/beam-mcp-tool.stub` at the same path in the host. (It is
  not `stubs/mcp-tool.stub`, which is laravel/mcp's override path.) The input class comes from beam's
  `stubs/particle-data.stub`, which `vendor:publish --tag=beam-stubs` publishes.

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
