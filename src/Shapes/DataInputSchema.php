<?php

namespace Splicewire\Beam\Mcp\Shapes;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Spatie\LaravelData\Attributes\MapInputName;

/**
 * Derives a tool's advertised input properties from its input Data class, so the Data class is the
 * single declared contract (`particle.undeclared-mcp-shape`) instead of a hand-authored `schema()`
 * closure drifting beside it.
 *
 * Lives in beam-mcp — beside the audit and the tool manifest — because every package that registers
 * an MCP tool (tower, beam-commerce, ...) must be able to reach the seam, and the only edge they all
 * share points DOWN into beam-mcp; tower cannot export it to beam-tier packages without a reverse
 * edge. The spatie {@see MapInputName} read degrades gracefully: the attribute class is only loaded
 * when a consumer's Data class actually carries it, so beam-mcp itself takes no spatie dependency.
 *
 * Everything structural reads off the constructor declaration: property order, wire name (via
 * spatie's {@see MapInputName} when the PHP name differs), JSON type from the PHP type, required-ness
 * from the absence of a default, and the advertised default from a non-null default value. Only what
 * PHP cannot express — description, array item type, enum values — comes from {@see Field}.
 */
class DataInputSchema
{
    /**
     * The property map for the given Data class, in the shape `Tool::schema()` would return —
     * built on the same {@see JsonSchema} factory so the emitted wire schema is byte-identical
     * to a hand-authored equivalent.
     *
     * @param  class-string  $dataClass
     * @return array<string, Type>
     */
    public static function properties(string $dataClass, JsonSchema $schema): array
    {
        $constructor = (new ReflectionClass($dataClass))->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $properties = [];

        foreach ($constructor->getParameters() as $parameter) {
            $properties[self::wireName($parameter)] = self::compile($parameter, $schema);
        }

        return $properties;
    }

    /**
     * The name the property carries on the wire — the spatie input mapping when present, the PHP
     * name otherwise.
     */
    protected static function wireName(ReflectionParameter $parameter): string
    {
        // MapInputName targets properties, so it must be read off the promoted property, not the parameter.
        $class = $parameter->getDeclaringClass();

        if ($class !== null && $class->hasProperty($parameter->getName())) {
            $mapped = $class->getProperty($parameter->getName())->getAttributes(MapInputName::class);

            if ($mapped !== []) {
                $input = $mapped[0]->newInstance()->input;

                if (is_string($input)) {
                    return $input;
                }
            }
        }

        return $parameter->getName();
    }

    protected static function compile(ReflectionParameter $parameter, JsonSchema $schema): Type
    {
        $field = self::field($parameter);
        $type = self::builderFor($parameter, $field, $schema);

        if ($field?->description !== null) {
            $type = $type->description($field->description);
        }

        if (! $parameter->isDefaultValueAvailable()) {
            $type = $type->required();
        } elseif ($parameter->getDefaultValue() !== null) {
            $type = $type->default($parameter->getDefaultValue());
        }

        if ($field?->enum !== null) {
            $type = $type->enum($field->enum);
        }

        return $type;
    }

    protected static function field(ReflectionParameter $parameter): ?Field
    {
        $attributes = $parameter->getAttributes(Field::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    protected static function builderFor(ReflectionParameter $parameter, ?Field $field, JsonSchema $schema): Type
    {
        $name = self::typeName($parameter);

        if ($name === 'array') {
            $array = $schema->array();

            return $field?->items === null ? $array : $array->items(self::scalar($field->items, $schema));
        }

        // An opaque JSON object the tool interprets itself (e.g. a typed declaration payload).
        if ($name === 'object') {
            return $schema->object();
        }

        return self::scalar($name, $schema);
    }

    protected static function typeName(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
            throw new RuntimeException(sprintf(
                'MCP input Data property [%s] must declare a single built-in type.',
                $parameter->getName(),
            ));
        }

        return $type->getName();
    }

    protected static function scalar(string $name, JsonSchema $schema): Type
    {
        return match ($name) {
            'string' => $schema->string(),
            'int' => $schema->integer(),
            'float' => $schema->number(),
            'bool' => $schema->boolean(),
            default => throw new RuntimeException(
                "Unsupported MCP input Data property type [{$name}]."
            ),
        };
    }
}
