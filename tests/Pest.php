<?php

use Splicewire\Beam\Mcp\Tests\ConfigDiscoveryTestCase;
use Splicewire\Beam\Mcp\Tests\TestCase;

// Pest's uses()->in() binds whichever declaration a file matches FIRST, with no directory-
// specificity override — so General/ and ConfigDriven/ are non-overlapping sibling directories,
// each bound to exactly one TestCase, and nothing is bound directly at the tests/ root.
uses(TestCase::class)->in(__DIR__.'/General');

// ConfigDriven/ pre-seeds beam.mcp.classes before boot, proving the config seam end-to-end.
uses(ConfigDiscoveryTestCase::class)->in(__DIR__.'/ConfigDriven');
