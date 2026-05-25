<?php

namespace Flames\Dumpper\Inc;

use Flames\Dumpper\Inc\DumpParser;

/**
 * Data transfer object holding the parsed representation of a single variable.
 *
 * @internal
 * @noinspection AutoloadingIssuesInspection
 */
class DumpVariableData
{
    /** @var string|null PHP type label */
    public ?string $type = null;

    /** @var string|null access modifier (public / protected / private) */
    public ?string $access = null;

    /** @var int|string|null variable or property name */
    public int|string|null $name = null;

    /** @var string|null operator shown between name and type (=> or ->) */
    public ?string $operator = null;

    /** @var int|string|null element count or "enum" */
    public int|string|null $size = null;

    /** @var array|string|null full expanded representation */
    public array|string|null $extendedValue = null;

    /** @var int|float|string|null short inline value */
    public int|float|string|null $value = null;

    /** @var array<string, string|array> extra tab views shown in rich mode */
    private array $alternativeRepresentations = [];

    /**
     * Adds an additional tab view for the same variable data (rich mode only).
     */
    public function addTabToView(mixed $originalVariable, string $tabName, mixed $value): void
    {
        if (is_array($value) && !(reset($value) instanceof self)) {
            $value = DumpParser::alternativesParse($originalVariable, $value);
        }

        $this->alternativeRepresentations[$tabName] = $value;
    }

    /**
     * Returns all representations merged with the main extendedValue first.
     *
     * @return array<string, mixed>
     */
    public function getAllRepresentations(): array
    {
        $prepared = [];

        if (!empty($this->extendedValue)) {
            $prepared['Contents'] = $this->extendedValue;
        }
        if (!empty($this->alternativeRepresentations)) {
            $prepared = array_merge($prepared, $this->alternativeRepresentations);
        }

        return $prepared;
    }
}
