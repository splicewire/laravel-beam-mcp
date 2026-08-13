<?php

namespace Splicewire\Beam\Mcp\Shapes;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Derives the tool's advertised input schema from its input Data class instead of a hand-authored
 * `schema()` override — the declared path `particle.undeclared-mcp-shape` audits for. The audit
 * reads a `schema()` override anywhere above `Laravel\Mcp\Server\Tool` as a hand-authored contract,
 * so the derivation deliberately replaces `inputSchema` in {@see Tool::toArray()}'s
 * result rather than declaring `schema()` — same factory, same serializer, byte-identical wire shape.
 */
trait DerivesInputSchemaFromData
{
    /**
     * The Data class declaring this tool's input contract.
     *
     * @return class-string
     */
    abstract protected function inputData(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        $schema = JsonSchemaFactory::object(
            fn (JsonSchema $schema): array => DataInputSchema::properties($this->inputData(), $schema),
        )->toArray();

        $schema['properties'] ??= (object) [];

        $array['inputSchema'] = $schema;

        return $array;
    }
}
