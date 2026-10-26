<?php

namespace Splicewire\Beam\Mcp\Tests\Integration;

// A cross-package base: laravel-beam-ux's own test environment (its tables, version store and providers) with this
// package's provider added. It exists only where that base is loadable (a run over laravel-beam-ux's vendor with its
// test namespace, as the EP-42 S1 proof runs); elsewhere the tests built on it skip, since this package takes no
// dependency on laravel-beam-ux.
if (class_exists(\Splicewire\Beam\Ux\Tests\AuthoredComponentNativeTestCase::class)) {
    abstract class HoldsBlocksTestCase extends \Splicewire\Beam\Ux\Tests\AuthoredComponentNativeTestCase
    {
        protected function getPackageProviders($app): array
        {
            return [...parent::getPackageProviders($app), \Splicewire\Beam\Mcp\BeamMcpServiceProvider::class];
        }
    }
} else {
    abstract class HoldsBlocksTestCase extends \PHPUnit\Framework\TestCase
    {
        protected function setUp(): void
        {
            $this->markTestSkipped('needs laravel-beam-ux and its test environment');
        }
    }
}
