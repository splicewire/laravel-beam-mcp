<?php

namespace Splicewire\Beam\Mcp\Console;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;
use Rushing\McpRegistry\Attributes\ToolAbility;
use Splicewire\Beam\Console\ParticleGeneratorCommand;
use Splicewire\Beam\Mcp\Attributes\McpTool;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Surgeon\McpToolShapeAudit;
use Symfony\Component\Console\Input\InputOption;

/**
 * `splicewire:beam:mcp:make:tool`: mints an MCP tool that starts declared: `#[McpTool]` group,
 * `#[ToolAbility]`, `DerivesInputSchemaFromData` with the input Data class it names, and an `outputSchema()`.
 *
 * ## Why not laravel/mcp's `make:mcp-tool`
 * That command emits a bare `Tool` with a hand-authored `schema()`, no group and no ability. Once listed, every
 * tool it generates is two `particle.undeclared-mcp-shape` findings (no output schema; a hand-authored input),
 * and until it is listed no server sees it at all. This one rides beam's generator chassis instead, so the
 * declaration slots are filled before the body is written and the run checks its own output.
 *
 * ## Its own stub, under its own name
 * The stub is `stubs/beam-mcp-tool.stub` in this package, overridable at the same path under the host's base
 * path. It is deliberately not `stubs/mcp-tool.stub`: that is laravel/mcp's override path, and a host that
 * customised laravel's command would otherwise feed this one. The input Data class comes from beam's
 * `stubs/particle-data.stub`, the same companion every beam generator writes, and is kept if it already exists.
 *
 * ## `--ability` has no default
 * The op generator defaults its ability from the op's kind, because a particle ability is a policy verb beam
 * knows. A tool's ability names the host's own vocabulary (tower grants `knowledge.read`, `compliance.disclose`,
 * `shop.transact`), which this command cannot know, and a default string would be granted or denied by
 * accident. So the command refuses without one and writes nothing.
 *
 * ## The check it runs on what it wrote
 * The chassis lints every emitted file and scans it for a central pin; {@see declarationFindings()} then
 * discovers the tool into the manifest, runs {@see McpToolShapeAudit} over it, and reflects its group and
 * ability. A host stub that drops `outputSchema()`, re-adds `schema()` or drops an attribute fails the run
 * (exit 1); `--no-verify` skips the check.
 *
 * ## What it deliberately does NOT do
 * It does not list the tool. Discovery reads `beam.mcp.classes` / `beam.mcp.discover_paths` (or a package
 * provider's own `discover()` call), so the command prints the config line and leaves the host's config alone.
 */
class MakeMcpToolCommand extends ParticleGeneratorCommand
{
    public const STUB = 'stubs/beam-mcp-tool.stub';

    protected $name = 'splicewire:beam:mcp:make:tool';

    protected $description = 'Scaffold an MCP tool with its #[McpTool] group, #[ToolAbility], derived input Data class and output schema declared.';

    protected $type = 'MCP tool';

    /** Resolved once in handle() so the stub tokens, the companion and the check agree. */
    protected string $resolvedGroup = '';

    protected string $resolvedAbility = '';

    protected string $inputClass = '';

    /**
     * This package's stub, unless the host published its own at the same relative path. The inherited
     * {@see resolveStubPath()} resolves against beam's root, which is right for the Data companion only.
     */
    protected function getStub(): string
    {
        $custom = $this->laravel->basePath(self::STUB);

        return file_exists($custom) ? $custom : dirname(__DIR__, 2).'/'.self::STUB;
    }

    /** The namespace laravel/mcp's own `make:mcp-tool` uses. */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Mcp\Tools';
    }

    /**
     * Declares `int`, mapping the framework's `false` (refused) to FAILURE, as every beam generator does: a
     * refusal that exits 0 is invisible to CI and to an agent reading the exit code.
     */
    public function handle(): int
    {
        $ability = $this->option('ability');

        if (! is_string($ability) || trim($ability) === '') {
            $this->components->error(
                '--ability is required: it is the #[ToolAbility] that gates listing and calling the tool, and it '
                .'names your host\'s ability vocabulary, which this command cannot know. Nothing was written.'
            );

            return self::FAILURE;
        }

        $this->resolvedAbility = trim($ability);

        $short = class_basename($this->getNameInput());
        $base = Str::endsWith($short, 'Tool') && $short !== 'Tool' ? Str::beforeLast($short, 'Tool') : $short;

        $group = $this->option('group');
        $this->resolvedGroup = is_string($group) && trim($group) !== '' ? trim($group) : Str::kebab($base);

        $qualified = $this->qualifyClass($this->getNameInput());
        $this->inputClass = $this->dataNamespace().'\\'.$base.'InputData';

        if (parent::handle() === false) {
            return self::FAILURE;
        }

        $this->writeCompanion('stubs/particle-data.stub', $this->inputClass, [
            'purpose' => sprintf(
                'The declared input shape of the `%s` MCP tool. DerivesInputSchemaFromData advertises these '
                .'constructor parameters as its `inputSchema`, and `handle()` hydrates this class from the request.',
                Str::kebab($short),
            ),
        ]);

        $this->newLine();
        $this->components->info('List it — #[McpTool] declares the group, the host lists the class:');
        $this->line(sprintf("    'classes' => [\\%s::class],", $qualified));
        $this->line('    in config/beam/mcp.php (beam.mcp.classes), or its directory under beam.mcp.discover_paths.');

        return $this->verified();
    }

    /**
     * The check this command's tests assert: the tool, discovered into the manifest as boot discovery would
     * discover it, sits in the requested group, has no `particle.undeclared-mcp-shape` finding, and is gated on
     * the requested ability.
     */
    protected function declarationFindings(): array
    {
        $class = array_key_first($this->emitted);
        $tool = $this->loadEmitted()[$class];

        // The audit skips anything that is not a Tool, so without this a stub that changed the parent class
        // would pass by not being audited.
        if (! is_subclass_of($tool, Tool::class)) {
            return [sprintf('%s does not extend %s, so no MCP server can serve it', $class, Tool::class)];
        }

        $reflection = new ReflectionClass($tool);
        $findings = [];

        $group = $reflection->getAttributes(McpTool::class)[0] ?? null;

        if ($group === null) {
            $findings[] = sprintf('%s carries no #[McpTool], so the manifest never lists it', $class);
        } else {
            $manifest = $this->laravel->make(McpToolManifest::class);
            $manifest->discover(classes: [$tool]);

            if (! in_array($tool, $manifest->classesFor($this->resolvedGroup), true)) {
                $findings[] = sprintf(
                    '%s was not discovered into the [%s] group (its #[McpTool] names [%s])',
                    $class,
                    $this->resolvedGroup,
                    $group->newInstance()->group,
                );
            }

            foreach ((new McpToolShapeAudit($manifest))->undeclared() as $row) {
                if ($row['tool'] === $tool) {
                    $findings[] = sprintf('%s %s (%s)', $class, $row['reason'], McpToolShapeAudit::CHECK);
                }
            }
        }

        $ability = $reflection->getAttributes(ToolAbility::class)[0] ?? null;
        $declared = $ability?->newInstance()->ability();

        if ($declared === null) {
            $findings[] = sprintf('%s declares no #[ToolAbility], so every caller may list and call it', $class);
        } elseif ($declared !== $this->resolvedAbility) {
            $findings[] = sprintf('%s is gated on [%s], not the requested [%s]', $class, $declared, $this->resolvedAbility);
        }

        return $findings;
    }

    /**
     * @return array<string, string>
     */
    protected function tokens(): array
    {
        return [
            'group' => $this->resolvedGroup,
            'ability' => $this->resolvedAbility,
            'inputClass' => class_basename($this->inputClass),
            'namespacedInputClass' => $this->inputClass,
        ];
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: int, 3: string, 4?: mixed}>
     */
    protected function getOptions(): array
    {
        return [
            ['group', null, InputOption::VALUE_REQUIRED, 'The #[McpTool] mount group (default: the kebab-cased <Name> without its Tool suffix)'],
            ['ability', 'a', InputOption::VALUE_REQUIRED, 'Required. The #[ToolAbility] that gates listing and calling the tool, in your host\'s ability vocabulary'],
            ['data-namespace', null, InputOption::VALUE_REQUIRED, 'Namespace for the emitted input Data class (default: App\Data)'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the tool class if it already exists'],
        ];
    }
}
