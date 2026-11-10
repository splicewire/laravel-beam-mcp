<?php

namespace Splicewire\Beam\Mcp\Tests\General;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\McpServiceProvider;
use Rushing\McpRegistry\Attributes\ToolAbility;
use Splicewire\Beam\Authorization\ActorPort;
use Splicewire\Beam\Config\ConfigSectionAccess;
use Splicewire\Beam\Data\Config\ConfigEnvData;
use Splicewire\Beam\Data\Config\ConfigKeyData;
use Splicewire\Beam\Data\Config\ConfigSectionCountsData;
use Splicewire\Beam\Data\Config\ConfigSectionData;
use Splicewire\Beam\Data\Config\ConfigSectionFilesData;
use Splicewire\Beam\Enums\ConfigEffect;
use Splicewire\Beam\Enums\ConfigMerge;
use Splicewire\Beam\Enums\ConfigTier;
use Splicewire\Beam\Mcp\Attributes\McpTool;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Surgeon\McpToolShapeAudit;
use Splicewire\Beam\Mcp\Tests\TestCase;
use Splicewire\Beam\Mcp\Tools\ReadConfigSectionsTool;

/**
 * `read-config-sections` is `beam-config` over MCP, and only a transport: the gate and the rows are core's
 * {@see ConfigSectionAccess} (tested in beam core against the REST read it must match, and end to end at the host).
 * What this file pins is the tool's own contract: its declared shape, its ability, that it serves exactly the rows
 * core hands it, and that core's refusal reaches the caller as an MCP error rather than an empty answer.
 *
 * Core's access is replaced with a recording fake here, because this package's harness deliberately does not boot
 * beam core (see TestCase).
 */
class ReadConfigSectionsToolTest extends TestCase
{
    /** @var array{0: Authenticatable|null, 1: string|null}|null */
    public static ?array $asked = null;

    /**
     * laravel/mcp fills a tool's `Request` with the call's arguments from a resolving hook its own provider
     * registers. A host auto-discovers that provider; testbench does not, and without it every argument reads
     * absent (measured here: `root` arrived null).
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    /** @param list<ConfigSectionData>|null $rows */
    private function access(?array $rows): void
    {
        self::$asked = null;

        $this->app->instance(ConfigSectionAccess::class, new class($rows) extends ConfigSectionAccess
        {
            /** @param list<ConfigSectionData>|null $rows */
            public function __construct(private ?array $answer) {}

            public function rows(?Authenticatable $actor, ?string $root = null): ?array
            {
                ReadConfigSectionsToolTest::$asked = [$actor, $root];

                return $this->answer;
            }
        });
    }

    private function actor(?Authenticatable $actor): void
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

    private function row(): ConfigSectionData
    {
        return new ConfigSectionData(
            id: 'app.meters',
            root: 'app.meters',
            title: 'Meters',
            description: 'Named cost dimensions.',
            owner: 'host',
            files: new ConfigSectionFilesData(package: null, host: 'config/app/meters.php'),
            schema: ['type' => 'object'],
            values: ['google.cse.query' => ['unit_cost_usd' => 0.005]],
            keys: [new ConfigKeyData(
                pointer: '/google.cse.query/unit_cost_usd',
                value: 0.005,
                tier: ConfigTier::Host,
                hostShadowsPackage: false,
                layers: [],
                env: new ConfigEnvData('GOOGLE_CSE_QUERY_COST_USD', false, 'config/app/meters.php', 40),
                effect: ConfigEffect::Deploy,
                ceiling: ConfigTier::Host,
                overridable: false,
                secret: false,
                set: true,
                merge: ConfigMerge::Replace,
            )],
            shadowedPackageKeys: [],
            counts: new ConfigSectionCountsData(keys: 1, overridable: 0, deploy: 1, secrets: 0, envBound: 1),
        );
    }

    public function test_it_is_fully_declared_and_names_the_resources_ability(): void
    {
        $manifest = new McpToolManifest;
        $manifest->discover([ReadConfigSectionsTool::class], []);

        $this->assertSame([], (new McpToolShapeAudit($manifest))->undeclared());

        $reflection = new \ReflectionClass(ReadConfigSectionsTool::class);
        $this->assertSame(['entitlement:os.operate'], $reflection->getAttributes(ToolAbility::class)[0]->getArguments());
        $this->assertSame(['config'], $reflection->getAttributes(McpTool::class)[0]->getArguments());
    }

    public function test_it_serves_exactly_the_rows_core_reads_for_the_calling_actor(): void
    {
        $operator = new User;
        $this->actor($operator);
        $this->access([$this->row()]);

        ConfigToolServer::tool(ReadConfigSectionsTool::class, ['root' => 'app.meters'])
            ->assertOk()
            ->assertHasNoErrors()
            ->assertStructuredContent(['sections' => [json_decode(json_encode($this->row()->toArray()), true)]]);

        $this->assertSame([$operator, 'app.meters'], self::$asked);
    }

    public function test_without_a_root_it_asks_for_every_section(): void
    {
        $this->actor(new User);
        $this->access([]);

        ConfigToolServer::tool(ReadConfigSectionsTool::class)->assertOk()->assertStructuredContent(['sections' => []]);

        $this->assertNull(self::$asked[1]);
    }

    public function test_a_refusal_is_an_error_not_an_empty_answer(): void
    {
        $this->actor(null);
        $this->access(null);

        ConfigToolServer::tool(ReadConfigSectionsTool::class)
            ->assertHasErrors(['Reading config sections requires the operator ability this install grants on `beam-config`.']);
    }
}

class ConfigToolServer extends Server
{
    protected array $tools = [ReadConfigSectionsTool::class];
}
