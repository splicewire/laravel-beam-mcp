<?php

namespace Splicewire\Beam\Mcp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Laravel\Mcp\Server\Tool;
use Splicewire\Beam\Mcp\McpToolManifest;
use Throwable;

/**
 * `GET beam/mcp/manifest.json` — the **advertised catalog** as JSON, the live-data half of beam-mcp's
 * docs contribution (ADR-0210 §3–§4).
 *
 * **It advertises; it does not resolve.** Per ADR-0145 the advertised catalog is the buyer-facing
 * SUPERSET of what the vendor offers, distinct from what *this tenant, this caller* may actually call.
 * The narrowed alternative is unavailable to a beam package regardless: tower's
 * `EffectiveManifestResolver` imports the commerce entitlement gate and the tenancy Tenant, so
 * per-caller resolution here would make beam-mcp require the commerce and tenancy stacks to render a
 * docs page — a worse violation of headless-installability than shipping a view.
 *
 * An optional per-caller availability **overlay** is left as a seam: a host that has a resolver supplies
 * marks and `<ManifestTable>` renders them when present. That is strictly better than swapping the list
 * — a reader learns the tool exists *and* that it is gated — and it keeps this response publicly
 * cacheable, avoiding the stale-authorization window `no-store` exists to close.
 *
 * **The wire shape is declared by `<ManifestTable>`, not here** (ticket 20): `{ items: [{name, title,
 * description}] }`. Extra keys are tolerated by the component and ignored; `name` is the stable machine
 * id and the row key. A 404 from this URL means *the contributor is uninstalled* and is the one signal
 * the component distinguishes from a broken endpoint — which is why this route mounts unconditionally
 * and returns an empty `items` list rather than 404ing when nothing is registered.
 */
class McpManifestController
{
    public function __invoke(McpToolManifest $manifest): JsonResponse
    {
        $items = [];

        foreach ($manifest->all() as $toolClass) {
            $item = $this->describe($toolClass);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        // Sorted by the stable machine id, so the document is byte-identical across boots: discovery
        // order follows the filesystem, which is not stable enough to serve or to cache against.
        usort($items, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return response()->json(['items' => $items]);
    }

    /**
     * One tool's advertised row. A class that cannot be constructed is OMITTED rather than fataling the
     * whole document: this is a marketing surface, and one broken tool must not take the reference page
     * down with it — the doctor's shape audit is where a malformed tool gets named.
     *
     * The `" Tool"` suffix is dropped from the title, carried over verbatim from the incumbent
     * `docs/mcp` route. It is presentation only — the registry manifest keeps the raw `Tool::title()`.
     *
     * @param  class-string  $toolClass
     * @return array{name: string, title: string, description: string}|null
     */
    private function describe(string $toolClass): ?array
    {
        try {
            $tool = app($toolClass);
        } catch (Throwable) {
            return null;
        }

        if (! $tool instanceof Tool) {
            return null;
        }

        $title = $tool->title();
        $suffix = ' Tool';

        if (str_ends_with($title, $suffix)) {
            $title = substr($title, 0, -strlen($suffix));
        }

        return [
            'name' => $tool->name(),
            'title' => $title,
            'description' => $tool->description(),
        ];
    }
}
