<?php

namespace Splicewire\Beam\Mcp\Tests\General;

use Rushing\Doctor\DoctorStatus;
use Splicewire\Beam\Doctor\BeamDoctorManifest;
use Splicewire\Beam\Mcp\McpToolManifest;
use Splicewire\Beam\Mcp\Surgeon\McpToolShapeAudit;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeDeclaredTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeHandAuthoredTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeOutputOnlyTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeRetrieveTool;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeSilentTool;
use Splicewire\Beam\Mcp\Tests\TestCase;
use Splicewire\Beam\Surgeon\UndeclaredSurfaceAudit;

/**
 * particle-doctrine-convergence ticket 06, MCP leg.
 *
 * MCP was the pattern-gap that started the effort: no declared path from the particle system to a tool, so
 * every tool hand-rolled its shapes. This audit makes that countable.
 */
class McpToolShapeAuditTest extends TestCase
{
    private function auditOver(string ...$tools): McpToolShapeAudit
    {
        $manifest = new McpToolManifest;
        $manifest->discover($tools, []);

        return new McpToolShapeAudit($manifest);
    }

    /** @return list<string> */
    private function reasonsFor(string $tool): array
    {
        return array_map(
            fn (array $row) => $row['reason'],
            array_values(array_filter($this->auditOver($tool)->undeclared(), fn (array $r) => $r['tool'] === $tool)),
        );
    }

    public function test_a_tool_declaring_no_output_schema_produces_a_finding(): void
    {
        $reasons = $this->reasonsFor(FakeHandAuthoredTool::class);

        $this->assertContains('declares no output schema, so its return shape is undeclared', $reasons);
    }

    public function test_a_tool_hand_authoring_its_input_schema_produces_a_finding(): void
    {
        $reasons = $this->reasonsFor(FakeHandAuthoredTool::class);

        $this->assertContains(
            'hand-authors its input schema instead of deriving it from a Data class',
            $reasons,
        );
    }

    public function test_the_prevailing_estate_shape_produces_both_findings(): void
    {
        // Hand-authored input plus no declared output is the shape essentially every live tool has.
        $this->assertCount(2, $this->reasonsFor(FakeHandAuthoredTool::class));
    }

    public function test_a_tool_taking_no_input_is_only_faulted_for_its_output(): void
    {
        // The two halves are NOT symmetric. An absent input schema means the tool takes no input — a complete
        // contract, not a missing one. But every tool RETURNS something, so an absent output schema is always
        // an undeclared shape; there is no "returns nothing" case for a tool the way there is for an HTTP verb.
        $this->assertSame(
            ['declares no output schema, so its return shape is undeclared'],
            $this->reasonsFor(FakeSilentTool::class),
        );
    }

    public function test_a_fully_declared_tool_is_not_a_finding(): void
    {
        $this->assertSame([], $this->reasonsFor(FakeDeclaredTool::class));
    }

    public function test_a_tool_declaring_output_but_hand_authoring_input_reports_only_the_input(): void
    {
        $this->assertSame(
            ['hand-authors its input schema instead of deriving it from a Data class'],
            $this->reasonsFor(FakeOutputOnlyTool::class),
        );
    }

    public function test_a_registered_class_that_is_not_an_mcp_tool_is_skipped(): void
    {
        // The manifest accepts any #[McpTool]-annotated class; only real Tool subclasses have schemas to
        // audit, so a non-Tool must not be reported as missing one.
        $this->assertSame([], $this->auditOver(FakeRetrieveTool::class)->undeclared());
    }

    public function test_each_finding_names_the_tool_and_its_location(): void
    {
        $row = $this->auditOver(FakeHandAuthoredTool::class)->undeclared()[0];

        $this->assertSame(FakeHandAuthoredTool::class, $row['tool']);
        $this->assertStringContainsString('FakeHandAuthoredTool.php', $row['location']);
        $this->assertSame(UndeclaredSurfaceAudit::TIER_GUIDED, $row['tier']);
    }

    public function test_it_reports_through_the_doctor_vocabulary(): void
    {
        $findings = $this->auditOver(FakeHandAuthoredTool::class)->run();

        $this->assertSame(McpToolShapeAudit::CHECK, $findings[0]->check);
        $this->assertSame(DoctorStatus::Warn, $findings[0]->status);
    }

    public function test_it_passes_when_every_tool_declares_its_shapes(): void
    {
        $findings = $this->auditOver(FakeDeclaredTool::class)->run();

        $this->assertCount(1, $findings);
        $this->assertSame(DoctorStatus::Pass, $findings[0]->status);
    }

    public function test_running_twice_produces_an_identical_sorted_result(): void
    {
        $audit = $this->auditOver(FakeOutputOnlyTool::class, FakeHandAuthoredTool::class, FakeDeclaredTool::class);

        $first = $audit->undeclared();

        $this->assertSame($first, $audit->undeclared());
        // Sorted by tool class, not discovery order — discovery follows the filesystem, which is not stable
        // enough to commit an artifact against.
        $this->assertSame(
            [FakeHandAuthoredTool::class, FakeHandAuthoredTool::class, FakeOutputOnlyTool::class],
            array_column($first, 'tool'),
        );
    }

    public function test_the_audit_registers_into_the_beam_doctor_manifest(): void
    {
        // Registered DOWN from this package's own provider, so beam-core never learns the consumer's name.
        $audits = array_map(
            fn ($registration) => $registration->audit,
            $this->app->make(BeamDoctorManifest::class)->registrations(),
        );

        $this->assertContains(McpToolShapeAudit::class, $audits);
    }
}
