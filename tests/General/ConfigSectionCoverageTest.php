<?php

use Splicewire\Beam\Config\ConfigSectionCoverage;
use Splicewire\Beam\Config\ConfigSectionRegistry;
use Splicewire\Beam\Mcp\Config\BeamMcpSection;

it('registers the beam.mcp config section from the service provider', function () {
    $registered = app(ConfigSectionRegistry::class)->get('beam.mcp');

    expect($registered)->not->toBeNull()
        ->and($registered->section)->toBe(BeamMcpSection::class)
        ->and($registered->owner)->toBe('splicewire/laravel-beam-mcp');
});

it('covers every beam.mcp key and carries no draft copy', function () {
    expect(ConfigSectionCoverage::uncovered(BeamMcpSection::class, config('beam.mcp')))->toBe([])
        ->and(ConfigSectionCoverage::drafts(BeamMcpSection::class))->toBe([]);
});
