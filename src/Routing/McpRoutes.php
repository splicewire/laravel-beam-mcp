<?php

namespace Splicewire\Beam\Mcp\Routing;

use Illuminate\Routing\Router;
use LogicException;
use Splicewire\Beam\Mcp\Http\Controllers\McpManifestController;
use Splicewire\Beam\Routing\ApiRoot;

final class McpRoutes
{
    // Ticket16 restores beam.mcp. when the legacy manifest exposure is removed.
    private const NAME_PREFIX = 'beam.mcp.api.';

    public static function mount(): void
    {
        ApiRoot::mount(app(Router::class), 'beam.mcp.api_root', 'mcp', function (Router $router): void {
            $groups = $router->getGroupStack();
            $group = $groups === [] ? [] : $groups[array_key_last($groups)];
            $uri = trim(($group['prefix'] ?? '').'/manifest.json', '/');
            foreach ($router->getRoutes() as $route) {
                if ($route->uri() !== $uri || $route->getDomain() !== ($group['domain'] ?? null) || ! in_array('GET', $route->methods(), true)) {
                    continue;
                }
                if (in_array($route->getActionName(), [McpManifestController::class, McpManifestController::class.'@__invoke'], true)
                    && Router::uniqueMiddleware($route->middleware()) === Router::uniqueMiddleware((array) ($group['middleware'] ?? []))) {
                    return;
                }
                throw new LogicException('MCP manifest exposure conflicts with existing route ['.($route->getName() ?? $uri).'].');
            }
            $router->get('manifest.json', McpManifestController::class)->name(self::NAME_PREFIX.'manifest');
        });
    }
}
