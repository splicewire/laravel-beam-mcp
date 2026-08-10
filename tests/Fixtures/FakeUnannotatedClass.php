<?php

namespace Splicewire\Beam\Mcp\Tests\Fixtures;

/**
 * Deliberately carries NO #[McpTool] attribute — proves scanPaths() ignores an un-annotated
 * class under a scanned directory instead of erroring, exactly as AdminResourceRegistry does.
 */
class FakeUnannotatedClass
{
    //
}
