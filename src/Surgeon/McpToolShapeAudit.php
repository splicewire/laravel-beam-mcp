<?php

namespace Splicewire\Beam\Mcp\Surgeon;

use Laravel\Mcp\Server\Tool;
use ReflectionMethod;
use Rushing\Doctor\DoctorAudit;
use Rushing\Doctor\Finding;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Surgeon\UndeclaredSurfaceAudit;

/**
 * The MCP leg of the negative-space detector (particle-doctrine-convergence ticket 06).
 *
 * MCP was the pattern-gap that started this whole effort: an agent asked to expose a capability over MCP had
 * no declared path from the particle system to a tool, so it hand-rolled one, and the hand-roll became
 * precedent. This audit makes that gap countable.
 *
 * It lives in beam-mcp rather than beam-core because the tool manifest does, and it registers DOWN into
 * beam-core's doctor manifest from this package's own provider — the same acyclic direction every other beam
 * package uses. beam-core never learns a consumer's name.
 *
 * Two distinct absences, because they are different work items:
 *
 *   - **Undeclared return shape.** `outputSchema()` is inherited from {@see Tool} rather than overridden, so
 *     the tool announces no output contract at all and a caller gets whatever the handler happened to format.
 *     This is the higher-yield half: at the time of writing, not one tool in the estate overrides it.
 *   - **Hand-authored input schema.** `schema()` IS overridden, but as a hand-written `JsonSchema` closure
 *     rather than derived from a declared Data class. The tool does have an input contract; it simply is not
 *     the one the invariant asks for, so it cannot feed the codegen chain and drifts from the HTTP surface
 *     that models the same thing.
 *
 * The two halves are deliberately NOT symmetric. An absent `schema()` means the tool takes no input, which is
 * a complete contract rather than a missing one — the same reason the HTTP leg distinguishes a verb that
 * legitimately returns nothing from an unresolvable one. But every tool RETURNS something, so an absent
 * `outputSchema()` is always an undeclared shape; there is no "returns nothing" case for a tool.
 *
 * Both findings tier as {@see UndeclaredSurfaceAudit::TIER_GUIDED}: choosing the Data class that should carry
 * a tool's input or output is an API-contract commitment, so it is a human's call, not a mechanical rewrite.
 */
class McpToolShapeAudit implements DoctorAudit
{
    public const CHECK = 'particle.undeclared-mcp-shape';

    public function __construct(private readonly McpToolManifest $manifest) {}

    /**
     * @return list<Finding>
     */
    public function run(): array
    {
        $rows = $this->undeclared();

        if ($rows === []) {
            return [Finding::pass(self::CHECK, 'Every registered MCP tool declares its input and output shape.')];
        }

        return array_map(
            fn (array $row) => Finding::warn(self::CHECK, sprintf(
                '[%s] MCP tool %s — %s at %s',
                $row['tier'],
                $row['tool'],
                $row['reason'],
                $row['location'],
            )),
            $rows,
        );
    }

    /**
     * The undeclared MCP surface, as sorted rows — the artifact payload.
     *
     * Sorted by tool class then reason so a re-run with no code change is byte-identical; manifest order
     * follows discovery, which follows the filesystem, which is not stable enough to commit against.
     *
     * @return list<array{tool: string, reason: string, tier: string, location: string}>
     */
    public function undeclared(): array
    {
        $rows = [];

        foreach ($this->manifest->all() as $tool) {
            if (! is_string($tool) || ! class_exists($tool) || ! is_subclass_of($tool, Tool::class)) {
                continue;
            }

            if (! $this->overrides($tool, 'outputSchema')) {
                $rows[] = $this->row($tool, 'declares no output schema, so its return shape is undeclared');
            }

            if ($this->overrides($tool, 'schema')) {
                $rows[] = $this->row($tool, 'hand-authors its input schema instead of deriving it from a Data class');
            }
        }

        usort($rows, fn (array $a, array $b) => [$a['tool'], $a['reason']] <=> [$b['tool'], $b['reason']]);

        return $rows;
    }

    /**
     * @param  class-string  $tool
     * @return array{tool: string, reason: string, tier: string, location: string}
     */
    private function row(string $tool, string $reason): array
    {
        return [
            'tool' => $tool,
            'reason' => $reason,
            'tier' => UndeclaredSurfaceAudit::TIER_GUIDED,
            'location' => $this->locate($tool),
        ];
    }

    /**
     * Whether the tool itself declares the method, as opposed to inheriting {@see Tool}'s default. Comparing
     * declaring classes is what separates "answered this question" from "never asked" — a subclass that
     * overrides it anywhere in its own hierarchy counts, which is why this tests against the base class
     * rather than the tool class.
     *
     * @param  class-string  $tool
     */
    private function overrides(string $tool, string $method): bool
    {
        if (! method_exists($tool, $method)) {
            return false;
        }

        return (new ReflectionMethod($tool, $method))->getDeclaringClass()->getName() !== Tool::class;
    }

    /** @param  class-string  $tool */
    private function locate(string $tool): string
    {
        $file = (new \ReflectionClass($tool))->getFileName();

        return $file === false ? $tool : $file;
    }
}
