<?php

use Splicewire\Beam\Mcp\Tests\ConfigDiscoveryTestCase;
use Splicewire\Beam\Mcp\Tests\TestCase;

// Pest's uses()->in() binds whichever declaration a file matches FIRST, with no directory-
// specificity override — so General/ and ConfigDriven/ are non-overlapping sibling directories,
// each bound to exactly one TestCase, and nothing is bound directly at the tests/ root.
uses(TestCase::class)->in(__DIR__.'/General');

// ConfigDriven/ pre-seeds beam.mcp.classes before boot, proving the config seam end-to-end.
uses(ConfigDiscoveryTestCase::class)->in(__DIR__.'/ConfigDriven');

/*
 * The published docs stub, `resource_path('beam-mcp/docs/mcp.mdx')`, is ONE file for every parallel worker: testbench
 * derives the resource path from its shared skeleton, so it cannot be made per-process. Three files write, delete or
 * read it (DocsStubFrontmatterTest, ProvenanceTemplatesTest, McpEndpointsTest), and which worker ran first decided
 * the result — ProvenanceTemplatesTest failed 2 runs in 3 at e286746 when a writer's copy appeared between its unlink
 * and its read. Those files hold this lock for each test, and release it with the published copy removed, so none
 * sees another's file.
 */
function lockPublishedDocsStub(): void
{
    $handle = fopen(sys_get_temp_dir().'/beam-mcp-tests.published-docs-stub.lock', 'c');
    flock($handle, LOCK_EX);
    $GLOBALS['beamMcpPublishedDocsStubLock'] = $handle;
}

function releasePublishedDocsStub(): void
{
    @unlink(resource_path('beam-mcp/docs/mcp.mdx'));

    $handle = $GLOBALS['beamMcpPublishedDocsStubLock'] ?? null;
    unset($GLOBALS['beamMcpPublishedDocsStubLock']);

    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
