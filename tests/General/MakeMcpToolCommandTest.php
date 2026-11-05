<?php

namespace Splicewire\Beam\Mcp\Tests\General;

use Closure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use ReflectionClass;
use Rushing\McpRegistry\Concerns\AuthorizesTools;
use Splicewire\Beam\Authorization\ActorPort;
use Splicewire\Beam\Console\ParticleGeneratorCommand;
use Splicewire\Beam\Mcp\Authorization\EntitlementToolAuthorizer;
use Splicewire\Beam\Mcp\Console\MakeMcpToolCommand;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Surgeon\McpToolShapeAudit;
use Splicewire\Beam\Mcp\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * make-surface 07: `splicewire:beam:mcp:make:tool`. The bar is the one beam's ParticleGeneratorTest sets for its
 * generators: a generated tool, listed, has no `particle.undeclared-mcp-shape` finding, and its ability gates it.
 * "A file appeared" is not the bar.
 *
 * Like that test, each case relocates the WHOLE base path to a throwaway host with a two-line `composer.json`:
 * `GeneratorCommand` derives its root namespace by matching `app_path()` against that psr-4 map, and the chassis's
 * central-pin scan would pass by exclusion inside the package tree. Each case also generates class names no other
 * case uses: a run loads what it wrote into this process, and a stale same-named class would let a case pass by
 * accident.
 */
class MakeMcpToolCommandTest extends TestCase
{
    private const COMMAND = 'splicewire:beam:mcp:make:tool';

    private string $host;

    /**
     * Plus laravel/mcp's own provider, which the package TestCase does not boot (testbench does not auto-discover).
     * Its `registerContainerCallbacks()` is what hands a tool's `handle(Request $request)` the call's arguments; without
     * it a `tools/call` reaches the handler with an empty request, and the calls below would prove nothing about them.
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // No 'test'/'fixture'/'stub' fragment in the name: those are audit-excluded path substrings.
        $this->host = sys_get_temp_dir().'/beam-mcp-gen-'.Str::random(10);

        File::ensureDirectoryExists($this->host.'/app');
        File::put($this->host.'/composer.json', json_encode(
            ['autoload' => ['psr-4' => ['App\\' => 'app/']]],
            JSON_PRETTY_PRINT,
        ));

        $this->app->setBasePath($this->host);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->host);

        parent::tearDown();
    }

    // ── what gets emitted ───────────────────────────────────────────────────────────────────────────

    public function test_it_emits_a_declared_tool_its_input_data_and_the_classes_line(): void
    {
        $this->artisan(self::COMMAND, ['name' => 'EmitProbeTool', '--group' => 'probe', '--ability' => 'probe.read'])
            ->expectsOutputToContain("'classes' => [\\App\\Mcp\\Tools\\EmitProbeTool::class],")
            ->assertExitCode(0);

        $tool = $this->read('app/Mcp/Tools/EmitProbeTool.php');

        $this->assertStringContainsString('namespace App\Mcp\Tools;', $tool);
        $this->assertStringContainsString("#[McpTool('probe')]", $tool);
        $this->assertStringContainsString("#[ToolAbility('probe.read')]", $tool);
        $this->assertStringContainsString('class EmitProbeTool extends Tool', $tool);
        $this->assertStringContainsString('use DerivesInputSchemaFromData;', $tool);
        $this->assertStringContainsString('use App\Data\EmitProbeInputData;', $tool);
        $this->assertStringContainsString('return EmitProbeInputData::class;', $tool);
        $this->assertStringContainsString('public function outputSchema(JsonSchema $schema): array', $tool);
        // The hand-authored input is the half of the audit laravel/mcp's own stub trips.
        $this->assertStringNotContainsString('function schema(', $tool);

        $data = $this->read('app/Data/EmitProbeInputData.php');

        $this->assertStringContainsString('namespace App\Data;', $data);
        $this->assertStringContainsString('class EmitProbeInputData extends Data', $data);
    }

    public function test_the_group_defaults_to_the_kebab_name_without_its_tool_suffix(): void
    {
        $this->artisan(self::COMMAND, ['name' => 'ListWidgetsTool', '--ability' => 'probe.read'])->assertExitCode(0);

        $this->assertStringContainsString("#[McpTool('list-widgets')]", $this->read('app/Mcp/Tools/ListWidgetsTool.php'));
        $this->assertFileExists($this->host.'/app/Data/ListWidgetsInputData.php');
    }

    public function test_a_data_namespace_moves_only_the_input_data_class(): void
    {
        $this->artisan(self::COMMAND, [
            'name' => 'MovedProbeTool',
            '--ability' => 'probe.read',
            '--data-namespace' => 'App\Mcp\Data',
        ])->assertExitCode(0);

        $this->assertStringContainsString('use App\Mcp\Data\MovedProbeInputData;', $this->read('app/Mcp/Tools/MovedProbeTool.php'));
        $this->assertStringContainsString('namespace App\Mcp\Data;', $this->read('app/Mcp/Data/MovedProbeInputData.php'));
    }

    // ── the acceptance criterion: a generated tool, listed, is conformance-clean ────────────────────

    public function test_a_generated_tool_listed_in_the_manifest_has_no_undeclared_mcp_shape(): void
    {
        $this->artisan(self::COMMAND, ['name' => 'CleanProbeTool', '--group' => 'clean', '--ability' => 'probe.read'])
            ->expectsOutputToContain('Verified')
            ->assertExitCode(0);

        // The command's run loaded the class; list it the way boot discovery would, in a manifest of its own.
        $tool = 'App\Mcp\Tools\CleanProbeTool';
        $this->assertTrue(class_exists($tool, false), 'The run did not load the tool it verified.');

        $manifest = new McpToolManifest;
        $manifest->discover(classes: [$tool]);

        // Guards the clean result below from passing because nothing was listed.
        $this->assertSame([$tool], $manifest->classesFor('clean'));
        $this->assertSame([], (new McpToolShapeAudit($manifest))->undeclared());

        // And the wire shape a server serves: the input derived from the Data class, the output declared.
        $wire = $this->app->make($tool)->toArray();

        $this->assertSame('clean-probe-tool', $wire['name']);
        $this->assertArrayHasKey('placeholder', (array) $wire['inputSchema']['properties']);
        $this->assertArrayHasKey('result', (array) $wire['outputSchema']['properties']);
    }

    // ── the generator checks its own output ─────────────────────────────────────────────────────────

    public function test_a_host_stub_that_drops_the_output_schema_exits_1(): void
    {
        $this->publishToolStub(
            'public function outputSchema(JsonSchema $schema): array',
            'public function declaredResult(JsonSchema $schema): array',
        );

        $this->artisan(self::COMMAND, ['name' => 'GhostOutputTool', '--ability' => 'probe.read'])
            ->expectsOutputToContain(McpToolShapeAudit::CHECK)
            ->assertExitCode(1);

        // The check reports; it does not roll back the author's tree.
        $this->assertFileExists($this->host.'/app/Mcp/Tools/GhostOutputTool.php');
    }

    public function test_a_host_stub_that_hand_authors_schema_exits_1(): void
    {
        $this->publishToolStub(
            "    use DerivesInputSchemaFromData;\n",
            "    use DerivesInputSchemaFromData;\n\n    public function schema(JsonSchema \$schema): array\n    {\n        return [];\n    }\n",
        );

        $this->artisan(self::COMMAND, ['name' => 'GhostSchemaTool', '--ability' => 'probe.read'])
            ->expectsOutputToContain(McpToolShapeAudit::CHECK)
            ->assertExitCode(1);
    }

    public function test_a_host_stub_that_drops_the_ability_exits_1(): void
    {
        $this->publishToolStub("#[ToolAbility('{{ ability }}')]\n", '');

        $this->artisan(self::COMMAND, ['name' => 'GhostAbilityTool', '--ability' => 'probe.read'])
            // One token with no space, so a wrapped bullet line cannot split it.
            ->expectsOutputToContain('#[ToolAbility]')
            ->assertExitCode(1);
    }

    public function test_no_verify_skips_the_check_and_exits_0_on_the_same_stub(): void
    {
        $this->publishToolStub(
            'public function outputSchema(JsonSchema $schema): array',
            'public function declaredResult(JsonSchema $schema): array',
        );

        $this->artisan(self::COMMAND, ['name' => 'PhantomTool', '--ability' => 'probe.read', '--no-verify' => true])
            ->assertExitCode(0);
    }

    public function test_force_over_an_already_loaded_tool_checks_the_bytes_just_written(): void
    {
        // The chassis rebind (Codex review 09 finding 1): the first run loads RebindTool into this process, as a
        // host's boot discovery would. The --force rerun rewrites it through an override that drops its output
        // schema, and the check must judge those bytes, not the definition already loaded.
        $args = ['name' => 'RebindTool', '--ability' => 'probe.read'];

        $this->artisan(self::COMMAND, $args)->assertExitCode(0);
        $this->assertTrue(class_exists('App\Mcp\Tools\RebindTool', false), 'The first run did not load the tool.');

        $this->publishToolStub(
            'public function outputSchema(JsonSchema $schema): array',
            'public function declaredResult(JsonSchema $schema): array',
        );

        $this->artisan(self::COMMAND, $args + ['--force' => true])
            ->expectsOutputToContain(McpToolShapeAudit::CHECK)
            ->assertExitCode(1);
    }

    // ── refusals and stub resolution ────────────────────────────────────────────────────────────────

    public function test_a_missing_ability_exits_1_and_writes_nothing(): void
    {
        $this->artisan(self::COMMAND, ['name' => 'UngatedProbeTool'])
            ->expectsOutputToContain('--ability is required')
            ->assertExitCode(1);

        $this->artisan(self::COMMAND, ['name' => 'UngatedProbeTool', '--ability' => ' '])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->host.'/app/Mcp/Tools/UngatedProbeTool.php');
        $this->assertFileDoesNotExist($this->host.'/app/Data/UngatedProbeInputData.php');
    }

    public function test_laravel_mcps_host_stub_does_not_feed_this_command(): void
    {
        // `stubs/mcp-tool.stub` is make:mcp-tool's override path. A host that customised laravel's command must
        // not change what this one emits.
        File::ensureDirectoryExists($this->host.'/stubs');
        File::put($this->host.'/stubs/mcp-tool.stub', "<?php\n\nnamespace {{ namespace }};\n\n// laravel-mcp-override\nclass {{ class }} {}\n");

        $this->artisan(self::COMMAND, ['name' => 'OverrideProbeTool', '--ability' => 'probe.read'])->assertExitCode(0);

        $tool = $this->read('app/Mcp/Tools/OverrideProbeTool.php');

        $this->assertStringNotContainsString('laravel-mcp-override', $tool);
        $this->assertStringContainsString("#[McpTool('override-probe')]", $tool);
    }

    // ── the ability gates the tool ──────────────────────────────────────────────────────────────────

    public function test_the_generated_ability_gates_the_tool_through_the_entitlement_authorizer(): void
    {
        // The EntitlementToolAuthorizerTest way: `AuthorizesTools` filters inside `createContext()`, the one
        // context both tools/list and tools/call read, so a tool absent from it is also refused if called.
        $this->artisan(self::COMMAND, ['name' => 'GateProbeTool', '--group' => 'probe', '--ability' => 'probe.write'])
            ->assertExitCode(0);

        $tool = 'App\Mcp\Tools\GateProbeTool';
        $granted = new class extends User {};
        $plain = new class extends User {};

        Gate::define('entitlement:probe.write', fn ($user = null) => $user === $granted);

        $this->actAs($granted);
        $this->assertContains('gate-probe-tool', $this->listedBy($tool));

        $this->actAs($plain);
        $this->assertNotContains('gate-probe-tool', $this->listedBy($tool));

        $this->actAs(null);
        $this->assertNotContains('gate-probe-tool', $this->listedBy($tool));
    }

    // ── the declared output is what tools/call answers (make-surface 07 amend, Codex review 07) ─────

    public function test_tools_call_on_a_generated_tool_returns_structured_content_matching_its_output_schema(): void
    {
        // Over the server's own message path, Server::handle() -> CallTool -> the JSON the transport is handed. A
        // text-only answer passed every case above while contradicting the outputSchema the tool advertises.
        $this->artisan(self::COMMAND, ['name' => 'ShapedProbeTool', '--group' => 'probe', '--ability' => 'probe.read'])
            ->assertExitCode(0);

        $tool = 'App\Mcp\Tools\ShapedProbeTool';
        $granted = new class extends User {};

        Gate::define('entitlement:probe.read', fn ($user = null) => $user === $granted);
        $this->actAs($granted);

        $result = $this->callOverTheWire($tool, ['placeholder' => 'shaped'])['result'] ?? null;

        $this->assertIsArray($result, 'tools/call returned no result.');
        $this->assertFalse($result['isError']);
        $this->assertArrayHasKey('structuredContent', $result, 'tools/call answered with text only: no structuredContent for the declared outputSchema.');

        // Judged against the tool's OWN declaration, as a client would read it from tools/list.
        $schema = $this->app->make($tool)->toArray()['outputSchema'];
        $structured = $result['structuredContent'];

        $this->assertNotSame([], $schema['required'] ?? []);

        foreach ($schema['required'] as $key) {
            $this->assertArrayHasKey($key, $structured, "structuredContent lacks the required [{$key}].");
        }

        foreach ($schema['properties'] as $key => $property) {
            if (array_key_exists($key, $structured) && ($property['type'] ?? null) === 'string') {
                $this->assertIsString($structured[$key], "structuredContent [{$key}] is not the declared string.");
            }
        }

        $this->assertSame([], array_diff_key($structured, $schema['properties']), 'structuredContent carries keys the outputSchema does not declare.');
        $this->assertSame('shaped', $structured['result']);

        // The text block carries the same object as JSON, for a client that reads only content.
        $this->assertSame($structured, json_decode($result['content'][0]['text'], true));
    }

    public function test_tools_call_keeps_the_ability_refusal_and_reports_a_tool_error_as_is_error(): void
    {
        // A host Data stub whose shape has a required field, so a call without it cannot hydrate the input and the
        // generated handler throws: the tool-error path. (An optional field coerces bad input to null instead.)
        $this->publishDataStubWithARequiredField();

        $this->artisan(self::COMMAND, ['name' => 'ErringProbeTool', '--group' => 'probe', '--ability' => 'probe.read'])
            ->assertExitCode(0);

        $tool = 'App\Mcp\Tools\ErringProbeTool';
        $granted = new class extends User {};
        $plain = new class extends User {};

        Gate::define('entitlement:probe.read', fn ($user = null) => $user === $granted);

        // Without the ability, tools/call is refused the way MCP refuses an absent tool.
        $this->actAs($plain);
        $refused = $this->callOverTheWire($tool, ['placeholder' => 'x']);

        $this->assertSame(-32602, $refused['error']['code'] ?? null);
        $this->assertArrayNotHasKey('result', $refused);

        // With it, a handler that throws (input that cannot hydrate the declared Data) is a tool error, unshaped.
        $this->actAs($granted);
        $failed = $this->callOverTheWire($tool, ['placeholder' => 'no topic'])['result'] ?? [];

        $this->assertTrue($failed['isError'] ?? false);
        $this->assertArrayNotHasKey('structuredContent', $failed);

        // And the same tool, given the field, answers in its declared shape.
        $answered = $this->callOverTheWire($tool, ['topic' => 'mcp', 'placeholder' => 'with topic'])['result'] ?? [];

        $this->assertFalse($answered['isError'] ?? true);
        $this->assertSame(['result' => 'with topic'], $answered['structuredContent'] ?? null);
    }

    // ── registration and formatting ─────────────────────────────────────────────────────────────────

    public function test_the_provider_registers_the_command_with_no_verify(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey(self::COMMAND, $commands);
        $this->assertInstanceOf(MakeMcpToolCommand::class, $commands[self::COMMAND]);
        $this->assertTrue($commands[self::COMMAND]->getDefinition()->hasOption('no-verify'));
    }

    public function test_the_generated_tree_is_already_formatter_clean(): void
    {
        $pint = dirname(__DIR__, 2).'/vendor/bin/pint';

        if (! is_file($pint)) {
            $this->markTestSkipped('pint is not installed in this checkout.');
        }

        $this->artisan(self::COMMAND, ['name' => 'FmtProbeTool', '--ability' => 'probe.read'])->assertExitCode(0);
        $this->artisan(self::COMMAND, [
            'name' => 'FmtMovedTool',
            '--ability' => 'probe.read',
            '--data-namespace' => 'App\Mcp\Data',
        ])->assertExitCode(0);

        $process = Process::fromShellCommandline(sprintf(
            'XDEBUG_MODE=off %s --test %s %s 2>&1',
            escapeshellarg($pint),
            escapeshellarg($this->host.'/app/Mcp'),
            escapeshellarg($this->host.'/app/Data'),
        ));
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Generated code is not pint-clean: '.$process->getOutput());
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────────────

    private function read(string $relative): string
    {
        $path = $this->host.'/'.$relative;

        $this->assertFileExists($path);

        return (string) File::get($path);
    }

    /** A host override of this command's stub (the `resolveStubPath` convention) with one edit applied. */
    private function publishToolStub(string $search, string $replace): void
    {
        $stub = File::get(dirname(__DIR__, 2).'/'.MakeMcpToolCommand::STUB);
        $edited = str_replace($search, $replace, $stub);

        $this->assertNotSame($stub, $edited, 'The tool stub no longer carries the text this test edits.');

        File::ensureDirectoryExists($this->host.'/stubs');
        File::put($this->host.'/'.MakeMcpToolCommand::STUB, $edited);
    }

    /** A host override of beam's Data stub (the companion this command writes) whose shape requires a `topic`. */
    private function publishDataStubWithARequiredField(): void
    {
        $beamRoot = dirname((string) (new ReflectionClass(ParticleGeneratorCommand::class))->getFileName(), 3);
        $stub = File::get($beamRoot.'/stubs/particle-data.stub');
        $edited = str_replace(
            '        // Replace with the fields this shape carries.',
            '        public string $topic,',
            $stub,
        );

        $this->assertNotSame($stub, $edited, "beam's Data stub no longer carries the line this test edits.");

        File::ensureDirectoryExists($this->host.'/stubs');
        File::put($this->host.'/stubs/particle-data.stub', $edited);
    }

    private function actAs(mixed $actor): void
    {
        $this->app->instance(ActorPort::class, new class($actor) implements ActorPort
        {
            public function __construct(private mixed $actor) {}

            public function actor(): mixed
            {
                return $this->actor;
            }
        });
    }

    /**
     * The tool names a server wired to the beam entitlement authorizer lists, mounting exactly `$tool`.
     *
     * @return list<string>
     */
    private function listedBy(string $tool): array
    {
        return $this->authorizedServer(new FakeTransporter, $tool)->createContext()->tools()->map(fn ($listed) => $listed->name())->values()->all();
    }

    /**
     * One `tools/call` through the server's own message path (`Server::handle()`, the method the stdio and HTTP
     * transports feed), returning the JSON-RPC message the transport was handed, decoded. A fresh server each call:
     * `AuthorizesTools` narrows the mounted tools inside `createContext()`.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function callOverTheWire(string $tool, array $arguments): array
    {
        $transport = new class implements Transport
        {
            /** @var list<string> */
            public array $sent = [];

            public function onReceive(Closure $handler): void {}

            public function run()
            {
                return null;
            }

            public function send(string $message, ?string $sessionId = null): void
            {
                $this->sent[] = $message;
            }

            public function sessionId(): ?string
            {
                return 'ms07';
            }

            public function stream(Closure $stream): void
            {
                $stream();
            }
        };

        $server = $this->authorizedServer($transport, $tool);
        $server->start();
        $server->handle((string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'ms07', 'version' => '0'],
        ]]));
        $server->handle((string) json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => [
            'name' => $this->app->make($tool)->name(), 'arguments' => $arguments,
        ]]));

        $answer = json_decode((string) end($transport->sent), true);

        $this->assertSame(2, $answer['id'] ?? null, 'The server sent no answer to the tools/call.');

        return $answer;
    }

    /** A server wired to the beam entitlement authorizer, mounting exactly `$tool`. */
    private function authorizedServer(Transport $transport, string $tool): Server
    {
        $server = new class($transport) extends Server
        {
            use AuthorizesTools;

            /** @param  list<class-string>  $tools */
            public function mount(array $tools): static
            {
                $this->tools = $tools;

                return $this;
            }

            protected function toolAuthorizer(): callable
            {
                return app(EntitlementToolAuthorizer::class);
            }
        };

        return $server->mount([$tool]);
    }
}
