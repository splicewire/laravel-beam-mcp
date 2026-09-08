<?php

namespace Splicewire\Beam\Mcp;

use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;

use InvalidArgumentException;
use ReflectionClass;
use Rushing\McpRegistry\ToolGroup;
use Rushing\Popcorn\Discovery\AttributedClassScanner;
use Splicewire\Beam\Mcp\Attributes\McpTool;

/**
 * The MCP tool-group registry. A host reads every registered group. Mirrors `Splicewire\Beam\Frame\
 * AdminResourceRegistry`'s `registerClass()`/`discover()`/`scanPaths()` shape one-for-one, applied
 * to `#[McpTool]` instead of `#[ParticleResource]`.
 *
 * ## The key is the GROUP, and one entry is one TOOL
 *
 * Registry-kernel ticket 38: the storage was `array<string, list<class-string>>` — one array slot
 * per group holding a list. On the kernel that is not one entry holding a list, it is MANY entries
 * admitted under one key ({@see OnKeyDuplicate::Admit}, which this class already declared). So
 * `beam.mcp.tools.shop` holds two entries when two tools carry `#[McpTool('shop')]`, `classesFor()`
 * is a `matches()` over that branch, and `grouped()` is rebuilt from `relativeKeys()` rather than
 * kept beside the entries. Sweep-brief §3e finding 2 is the same shape from the config side.
 *
 * Idempotence is preserved explicitly rather than by `array_unique`: `register()` skips a class
 * already admitted under that group, so a re-scanned or re-registered class never duplicates —
 * matching `AdminResourceRegistry`'s "re-scanning overwrites/dedupes, never duplicates" contract.
 * `Admit` would otherwise happily store it twice.
 *
 * @implements Registry<class-string>
 */
#[IsRegistry(
    root: 'beam.mcp.tools',
    entryType: 'class-string<Laravel\Mcp\Server\Tool>',
    onKeyDuplicate: OnKeyDuplicate::Admit,
    description: 'MCP tool classes grouped by mount group, exposed to a host MCP server\'s groups(). Filled by an #[McpTool] attribute scan. Admit because a group legitimately holds many tools — the key is the GROUP, not the tool. Registering the same class into the same group twice is a no-op, which Admit does not give you for free.',
    order: 16,
)]
class McpToolManifest implements Gated, Registry
{
    /** @var BasicRegistry<class-string> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Admit one tool class — or a list of them — under a mount-group key.
     *
     * The entry is WIDENED to accept a list rather than the contract's single value, because the
     * shipped config shape (`group => [class, class]`) is a list per key and `Admit` is what turns
     * that into one entry per element. The STORED entry is always one class-string, so `entryType`
     * stays a class-string.
     *
     * Already-admitted classes are skipped, which is what keeps a repeated `discover()` idempotent.
     *
     * @param  class-string|list<class-string>  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        // Registration is never gated (kernel `Registry` docblock), so the duplicate check reads
        // through `unfiltered()` — an authorizer installed by the host must not make an
        // already-registered class look absent and get admitted a second time.
        $existing = $this->entries->unfiltered()->matches($key);

        foreach (is_array($entry) ? array_values($entry) : [$entry] as $toolClass) {
            if (in_array($toolClass, $existing, true)) {
                continue;
            }

            $this->entries->register($key, $toolClass, $by, $ability);
            $existing[] = $toolClass;
        }

        return $this;
    }

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

        $this->register($attribute->group, $toolClass, by: $toolClass);
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
    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    /**
     * The registered tool classes for one group (empty list if the group has no entries) — the
     * port's own vocabulary, sugar over `matches()`.
     *
     * @return list<class-string>
     */
    public function classesFor(string $group): array
    {
        return $this->entries->matches($group);
    }

    /**
     * The full registry, grouped — the raw read shape, useful for inspection/testing without
     * constructing `ToolGroup` value objects. Rebuilt from {@see BasicRegistry::relativeKeys()} so
     * groups come back in the caller's spelling, in registration order (ticket 20 D2).
     *
     * @return array<string, list<class-string>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->entries->relativeKeys() as $group) {
            $grouped[$group] = $this->classesFor($group);
        }

        return $grouped;
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
        $grouped = $this->grouped();

        return array_map(
            static fn (string $group, array $classes): ToolGroup => ToolGroup::make($group, $classes),
            array_keys($grouped),
            array_values($grouped),
        );
    }

    /**
     * Every registered tool class across every group, de-duplicated — a flat enumeration.
     * Group-major rather than raw registration order, which is the shape this has always returned.
     *
     * @return list<class-string>
     */
    public function all(): array
    {
        $grouped = $this->grouped();

        if ($grouped === []) {
            return [];
        }

        return array_values(array_unique(array_merge(...array_values($grouped))));
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
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
