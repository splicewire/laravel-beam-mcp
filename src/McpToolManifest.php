<?php

namespace Splicewire\Beam\Mcp;

use InvalidArgumentException;
use ReflectionClass;
use Rushing\McpRegistry\ToolGroup;
use Rushing\Popcorn\Discovery\AttributedClassScanner;
use Splicewire\Beam\Mcp\Attributes\McpTool;

/**
 * The MCP tool-group registry (beam-manifest-index: `attribute-scan` seam, `run-all` arity — a
 * host reads EVERY registered group, not one by key). Mirrors `Splicewire\Beam\Frame\
 * AdminResourceRegistry`'s `registerClass()`/`discover()`/`scanPaths()` shape one-for-one, applied
 * to `#[McpTool]` instead of `#[ParticleResource]`.
 *
 * Stores grouped class-strings, keyed by group name — `array_unique` per group makes
 * `registerClass()` idempotent (a re-scanned/re-registered class never duplicates), matching
 * `AdminResourceRegistry`'s "re-scanning overwrites/dedupes, never duplicates" contract.
 */
class McpToolManifest
{
    /** @var array<string, list<class-string>> */
    private array $tools = [];

    /**
     * Reflect a `#[McpTool]`-annotated class and register it into its declared group.
     *
     * @param  class-string  $toolClass
     */
    public function registerClass(string $toolClass): void
    {
        if (! class_exists($toolClass)) {
            throw new InvalidArgumentException("MCP tool class [{$toolClass}] does not exist.");
        }

        $attribute = $this->readAttribute($toolClass);

        if ($attribute === null) {
            throw new InvalidArgumentException(
                "Class [{$toolClass}] is not annotated with #[McpTool]; annotate it or drop the discover-path entry."
            );
        }

        $group = $attribute->group;
        $existing = $this->tools[$group] ?? [];
        $existing[] = $toolClass;
        $this->tools[$group] = array_values(array_unique($existing));
    }

    /**
     * Scan configured class-strings and discover-paths for `#[McpTool]` classes.
     * Idempotent — re-scanning overwrites by group, never duplicates a class within a group.
     *
     * @param  array<int, class-string>  $classes  explicit tool class list
     * @param  array<int, string>  $paths  filesystem paths to scan for annotated classes
     */
    public function discover(array $classes = [], array $paths = []): void
    {
        foreach ($classes as $class) {
            $this->registerClass($class);
        }

        foreach ($this->scanPaths($paths) as $class) {
            $this->registerClass($class);
        }
    }

    /**
     * Find `#[McpTool]`-annotated class-strings under the given paths — delegates the generic
     * file→FQCN→attribute walk to popcorn's {@see AttributedClassScanner}, exactly as
     * `AdminResourceRegistry::scanPaths()` does for `#[ParticleResource]`.
     *
     * @param  array<int, string>  $paths
     * @return list<class-string>
     */
    public function scanPaths(array $paths): array
    {
        $scanner = new AttributedClassScanner;

        return array_values(array_unique(
            $scanner->scan($paths, McpTool::class, instanceof: false),
        ));
    }

    /**
     * Whether any tool is registered under this group.
     */
    public function has(string $group): bool
    {
        return isset($this->tools[$group]);
    }

    /**
     * The registered tool classes for one group (empty list if the group has no entries).
     *
     * @return list<class-string>
     */
    public function classesFor(string $group): array
    {
        return $this->tools[$group] ?? [];
    }

    /**
     * The full registry, grouped — the raw read shape, useful for inspection/testing without
     * constructing `ToolGroup` value objects.
     *
     * @return array<string, list<class-string>>
     */
    public function grouped(): array
    {
        return $this->tools;
    }

    /**
     * Every registered group as a `Rushing\McpRegistry\ToolGroup` — the shape a host MCP server's
     * `groups()` method returns directly (`SplicewireServer::groups()` becomes
     * `app(McpToolManifest::class)->toolGroups()`, no per-group hand-listing).
     *
     * @return list<ToolGroup>
     */
    public function toolGroups(): array
    {
        return array_map(
            static fn (string $group, array $classes): ToolGroup => ToolGroup::make($group, $classes),
            array_keys($this->tools),
            array_values($this->tools),
        );
    }

    /**
     * Every registered tool class across every group, de-duplicated — the flat run-all read.
     *
     * @return list<class-string>
     */
    public function all(): array
    {
        if ($this->tools === []) {
            return [];
        }

        return array_values(array_unique(array_merge(...array_values($this->tools))));
    }

    /**
     * @param  class-string  $toolClass
     */
    private function readAttribute(string $toolClass): ?McpTool
    {
        $reflection = new ReflectionClass($toolClass);
        $attributes = $reflection->getAttributes(McpTool::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
