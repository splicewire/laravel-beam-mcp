# splicewire/laravel-beam-mcp

The MCP arm of the schemastud beam family: any beam-tier package exposes an MCP tool by
annotating the tool class `#[McpTool('group')]` — no Tower-side edit required.

## The seam

Today, a host MCP server (e.g. `Splicewire\Tower\Mcp\Servers\SplicewireServer`) hand-lists every
tool class it exposes, reaching directly into other packages' `Mcp\*` namespaces. This package
inverts that: a package registers itself.

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
(`config/beam/mcp.php`), and it's discovered at boot into the `McpToolManifest` singleton — a
`splicewire/laravel-beam` **attribute-scan / run-all** registry (see
`splicewire:beam:manifests`), self-described into beam-core's `ManifestIndex`.

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
