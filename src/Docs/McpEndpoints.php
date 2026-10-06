<?php

namespace Splicewire\Beam\Mcp\Docs;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Laravel\Mcp\Facades\Mcp;
use ReflectionFunction;
use Throwable;

/**
 * The MCP servers this deployment actually mounts, as a reader would copy them (docs-walkthrough DOC-11(d), DOCS-08).
 *
 * Read from the route table (`Mcp::servers()`'s web servers), never guessed. The seeder used to print `app.url` + `/mcp`
 * on every host, and all three live hosts answered 404 there. Each mounted server gives:
 * - its endpoint: absolute on `app.url`, or a `https://{tenant}.<central domain>/<uri>` TEMPLATE when the route
 *   initializes tenancy by subdomain, since there is no single URL to copy then;
 * - the guard its `auth:` middleware names (bare `auth` is the default guard);
 * - the client key, from the server class (`GatedShopServer` → `gated-shop`).
 * When none is mounted, it says so instead of printing a URL.
 */
class McpEndpoints
{
    private const SUBDOMAIN_TENANCY = ['InitializeTenancyBySubdomain', 'InitializeTenancyByDomainOrSubdomain'];

    /** @return list<array{url: string, guard: ?string, key: string, server: ?string}> */
    public function all(): array
    {
        $out = [];

        $keys = [];
        foreach (Mcp::servers() as $server) {
            if ($server instanceof Route) {
                $described = $this->describe($server);
                // One server class mounted twice (the flagship's `mcp` and its local-only `mcp-local`) would collide
                // in a client config, so a repeat is suffixed with its route's slug.
                if (isset($keys[$described['key']])) {
                    $described['key'] .= '-'.Str::slug($server->uri());
                }
                $keys[$described['key']] = true;
                $out[] = $described;
            }
        }

        return $out;
    }

    /** The markdown a docs page interpolates as `{{ mcp_servers }}`. */
    public function markdown(): string
    {
        $servers = $this->all();

        if ($servers === []) {
            return 'No MCP server is mounted on this deployment, so there is no endpoint to connect a client to yet. '
                .'A host mounts one with `Mcp::web(...)`; this page lists it once it is in the route table.';
        }

        $lines = [];
        $config = [];
        foreach ($servers as $server) {
            $auth = $server['guard'] !== null ? "authenticated by the `{$server['guard']}` guard" : 'with no authentication middleware';
            $tenant = str_contains($server['url'], '{tenant}') ? ' Replace `{tenant}` with your workspace\'s subdomain.' : '';
            $lines[] = "- `{$server['url']}`, {$auth}.{$tenant}";
            $config[$server['key']] = ['url' => $server['url']];
        }

        return implode("\n", $lines)."\n\n```json\n".json_encode(['mcpServers' => $config], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```";
    }

    /**
     * The one endpoint a host stub that still writes `{{ endpoint_url }}` gets: the first GUARDED server, so a keyless
     * local-only mount (the flagship's `mcp-local`) is never the one a reader copies, else the first server, null when none
     * (build.qa).
     */
    public function primary(): ?string
    {
        $servers = $this->all();
        foreach ($servers as $server) {
            if ($server['guard'] !== null) {
                return $server['url'];
            }
        }

        return $servers[0]['url'] ?? null;
    }

    /** @return array{url: string, guard: ?string, key: string, server: ?string} */
    private function describe(Route $route): array
    {
        $middleware = array_map(fn (mixed $m): string => is_string($m) ? $m : (is_object($m) ? $m::class : ''), $route->gatherMiddleware());
        $server = $this->serverClass($route);

        return [
            'url' => $this->url($route, $middleware),
            'guard' => $this->guard($middleware),
            'key' => $server !== null ? Str::kebab(Str::beforeLast(class_basename($server), 'Server') ?: class_basename($server)) : Str::slug($route->uri()),
            'server' => $server,
        ];
    }

    /** @param  list<string>  $middleware */
    private function url(Route $route, array $middleware): string
    {
        $app = parse_url((string) config('app.url')) ?: [];
        $scheme = $app['scheme'] ?? 'https';
        $path = '/'.ltrim($route->uri(), '/');

        if ($route->getDomain() !== null) {
            return "{$scheme}://".$route->getDomain().$path;
        }

        foreach ($middleware as $m) {
            if (Str::endsWith($m, self::SUBDOMAIN_TENANCY)) {
                $central = ((array) config('tenancy.central_domains', []))[0] ?? ($app['host'] ?? 'localhost');

                return "{$scheme}://{tenant}.{$central}{$path}";
            }
        }

        return rtrim((string) config('app.url'), '/').$path;
    }

    /** @param  list<string>  $middleware */
    private function guard(array $middleware): ?string
    {
        foreach ($middleware as $m) {
            if ($m === 'auth') {
                return (string) config('auth.defaults.guard');
            }
            if (str_starts_with($m, 'auth:')) {
                return explode(',', substr($m, 5))[0];
            }
        }

        return null;
    }

    /** The server class `Mcp::web()` captured in the route's closure; null if it cannot be read. */
    private function serverClass(Route $route): ?string
    {
        try {
            $uses = $route->getAction('uses');
            if (! $uses instanceof \Closure) {
                return null;
            }
            $class = (new ReflectionFunction($uses))->getClosureUsedVariables()['serverClass'] ?? null;

            return is_string($class) ? $class : null;
        } catch (Throwable) {
            return null;
        }
    }
}
