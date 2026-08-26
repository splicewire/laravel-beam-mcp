<?php

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Support\DataConfig;
use Splicewire\Beam\Mcp\Shapes\DataInputSchema;
use Splicewire\Beam\Mcp\Tests\Fixtures\FakeToolInputData;

/**
 * The executability gate for beam-mcp's laravel-data seam (api-surface-coherence ticket 85).
 *
 * Testbench does not auto-discover: a harness boots exactly what `getPackageProviders()` names,
 * while `src/` imports whatever it can autoload. `Shapes\DataInputSchema` imports
 * `Spatie\LaravelData\Attributes\MapInputName`, so this package consumes laravel-data's reflection
 * machinery directly — and before ticket 85 `tests/TestCase.php` never registered
 * `LaravelDataServiceProvider`, leaving `config('data')` NULL (measured) so anything reaching
 * laravel-data's own config path fatals rather than fails. Ticket 84 measured the identical defect
 * in `splicewire/tower`.
 *
 * Booting the provider is only half of it. The package ships `name_mapping_strategy.input => null`;
 * the host that runs this code (`~/Herd/splicewire-app/config/data.php`) sets CamelCaseMapper. For
 * THIS package that delta is the whole subject — `DataInputSchema` derives a tool's advertised
 * input NAMES — so a harness on the package default would prove a mapping regime nobody runs.
 */
function beamMcpInputDataClasses(): array
{
    $root = dirname(__DIR__, 2).'/src';

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    $classes = [];

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (substr($path, -4) !== '.php') {
            continue;
        }

        // Namespace is the path under src/, PSR-4'd onto Splicewire\Beam\Mcp\.
        $relative = substr($path, strlen($root) + 1, -4);
        $class = 'Splicewire\\Beam\\Mcp\\'.str_replace('/', '\\', $relative);

        if (! class_exists($class) || ! is_subclass_of($class, BaseData::class)) {
            continue;
        }

        if ((new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $classes[$class] = [$class];
    }

    // beam-mcp owns the DERIVATION, not the DTOs — consumers (tower, beam-commerce, ...) declare
    // those. So src/ legitimately holds zero Data classes today and the fixture below is what keeps
    // the gate from being vacuous; the walk exists to pick up any that land here later.
    $classes[FakeToolInputData::class] = [FakeToolInputData::class];

    ksort($classes);

    return $classes;
}

it('has the laravel-data provider booted, mirroring the host config', function () {
    // Guards the fix itself. Without the provider `config('data')` is null and everything below
    // fatals rather than fails — a much worse signal.
    expect(config('data'))->not->toBeNull();

    // `config('data')` being non-null is NOT a valid probe that the provider booted, and this test
    // used to rest on it. `defineEnvironment()` sets `data.*` keys itself, which MATERIALISES the
    // config array whether or not the provider ever registered. Measured in
    // `splicewire/laravel-composition-music-spine` (ticket 85): with the provider commented out,
    // `config('data')` returned `['name_mapping_strategy', 'structure_caching']` and the assertion
    // above stayed green. The provider's own registration is the only thing that proves it booted.
    expect(app()->getLoadedProviders())->toHaveKey(LaravelDataServiceProvider::class);

    // And a key only the PROVIDER supplies, which `defineEnvironment()` does not. Without it
    // `getValidationRules()` does not throw — it DEGRADES SILENTLY to rule-less arrays, so the
    // data-driven cases below would pass while proving nothing.
    expect(config('data.rule_inferrers'))->not->toBeEmpty();

    // And guards the FALSE GREEN: the package default is `null`, only the host sets the mapper.
    expect(config('data.name_mapping_strategy.input'))->toBe(CamelCaseMapper::class);
});

it('finds input Data classes to check', function () {
    // A discovery bug that found nothing would make the cases below vacuous.
    expect(count(beamMcpInputDataClasses()))->toBeGreaterThan(0);
});

it('can analyse and derive validation rules for every declared input Data class', function (string $class) {
    $dataClass = app(DataConfig::class)->getDataClass($class);

    expect($dataClass->name)->toBe($class);

    expect($class::getValidationRules([]))->toBeArray();
})->with(beamMcpInputDataClasses());

it('projects an input Data class into an MCP wire schema under the host mapping regime', function () {
    $properties = JsonSchemaFactory::object(
        fn (JsonSchema $schema): array => DataInputSchema::properties(FakeToolInputData::class, $schema),
    )->toArray();

    // Explicit MapInputName wins outright; the unmapped property keeps its declared PHP name, which
    // is already camelCase under the host's input mapper — the two agree only because the harness
    // mirrors the host.
    expect(array_keys($properties['properties']))
        ->toBe(['shop_id', 'productSku', 'tags', 'limit']);

    expect($properties['properties']['shop_id']['type'])->toBe('string');
    expect($properties['properties']['tags']['type'])->toBe('array');
    expect($properties['properties']['limit']['default'])->toBe(25);
    expect($properties['required'])->toBe(['shop_id', 'productSku']);
});

it('maps the same property names laravel-data itself resolves under the host mapper', function () {
    // The real proof that the mapping regime is the host's: laravel-data's own analysis of the
    // fixture — the machinery that was unreachable while `config('data')` was null — resolves the
    // explicitly mapped name and leaves the camelCase property untouched under CamelCaseMapper.
    $dataClass = app(DataConfig::class)->getDataClass(FakeToolInputData::class);

    $mapped = [];

    foreach ($dataClass->properties as $property) {
        $mapped[$property->name] = $property->inputMappedName ?? $property->name;
    }

    expect($mapped['shopId'])->toBe('shop_id');
    expect($mapped['productSku'])->toBe('productSku');
});
