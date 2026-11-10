<?php

namespace Splicewire\Beam\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Rushing\McpRegistry\Attributes\ToolAbility;
use Splicewire\Beam\Authorization\ActorPort;
use Splicewire\Beam\Authorization\AuthenticatedActor;
use Splicewire\Beam\Config\ConfigSectionAccess;
use Splicewire\Beam\Data\Config\ConfigSectionData;
use Splicewire\Beam\Mcp\Attributes\McpTool;
use Splicewire\Beam\Mcp\Data\ReadConfigSectionsInputData;
use Splicewire\Beam\Mcp\Shapes\DerivesInputSchemaFromData;

/**
 * The `beam-config` particle resource's rows over MCP (beam ADR-0242 decision 8): every registered Config Section,
 * or one by root, with each key's effective value and provenance. Scaffolded by `splicewire:beam:mcp:make:tool`.
 *
 * It is a TRANSPORT and nothing else. The gate and the rows are core's {@see ConfigSectionAccess}, which asks the
 * resource's own declared read gate and reads through the one `ConfigSectionReader` the REST and Frame reads use,
 * so a row cannot differ by transport and a caller the API refuses is refused here.
 *
 *   - ability: #[ToolAbility] names the same ability `beam-config` declares (`entitlement:os.operate`), so a server
 *              using AuthorizesTools hides the tool from callers without it. The call itself is gated again by
 *              the resource, because a server's tool authorizer may be allow-all;
 *   - input:   {@see ReadConfigSectionsInputData}, an optional root;
 *   - output:  `{sections: ConfigSectionData[]}`, the resource's own rows.
 *
 * A host opts in by listing the class (`beam.mcp.classes`, or `McpToolManifest::discover()` from its provider), like
 * any beam-mcp tool; which tenants mount the `config` group is then the host's mount axis.
 */
#[Description('Read this install\'s Config Sections: each config key\'s effective value, the tier that set it (package, host, provider), whether the host shadows the package, and the env var that feeds it. Pass `root` (e.g. `app.meters`) for one section. Read-only; secrets show only whether they are set.')]
#[ToolAbility('entitlement:os.operate')]
#[McpTool('config')]
class ReadConfigSectionsTool extends Tool
{
    use DerivesInputSchemaFromData;

    /**
     * The public MCP name, explicit because the derived one (`read-config-sections-tool`, from the class name) would ship
     * on a host's advertised catalog (`/docs/mcp`), an interface a host then has to keep. It follows the catalog's
     * convention, a snake_case verb phrase (`search`, `list_silos`, `get_source`, `list_offers`).
     */
    protected string $name = 'read_config_sections';

    public function handle(Request $request, ConfigSectionAccess $access, ActorPort $actors): ResponseFactory
    {
        $input = ReadConfigSectionsInputData::from($request->all());
        $rows = $access->rows(AuthenticatedActor::from($actors), $input->root);

        if ($rows === null) {
            return Response::make(Response::error('Reading config sections requires the operator ability this install grants on `beam-config`.'));
        }

        return Response::structured([
            'sections' => array_map(fn (ConfigSectionData $row): array => $row->toArray(), $rows),
        ]);
    }

    protected function inputData(): string
    {
        return ReadConfigSectionsInputData::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'sections' => $schema->array()
                ->items($schema->object())
                ->description('One `beam-config` row per Config Section, ordered by root: identity, JSON Schema, effective values (secrets redacted) and each key\'s provenance.')
                ->required(),
        ];
    }
}
