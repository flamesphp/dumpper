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
    public $type;
    /** @var string|null access modifier (public / protected / private) */
    public $access;
    /** @var string|null variable or property name */
    public $name;
    /** @var string|null operator shown between name and type (=> or ->) */
    public $operator;
    /** @var int|string|null element count or "enum" */
    public $size;
    /** @var array|string|null full expanded representation */
    public $extendedValue;
    /** @var string|null short inline value */
    public $value;

    /** @var array<string, string|array> extra tab views shown in rich mode */
    private $alternativeRepresentations = array();

    /**
     * Adds an additional tab view for the same variable data (rich mode only).
     *
     * @param mixed        $originalVariable the variable being parsed (used for recursion detection)
     * @param string       $tabName          label displayed on the tab
     * @param string|array $value            tab contents
     *
     * @return void
     */
    public function addTabToView($originalVariable, $tabName, $value)
    {
        if (is_array($value)) {
            if (!(reset($value) instanceof self)) {
                $value = DumpParser::alternativesParse($originalVariable, $value);
            }
        }

        $this->alternativeRepresentations[$tabName] = $value;
    }

    /**
     * Returns all representations merged with the main extendedValue first.
     *
     * @return array<string, mixed>
     */
    public function getAllRepresentations()
    {
        $prepared = array();

        if (!empty($this->extendedValue)) {
            $prepared['Contents'] = $this->extendedValue;
        }
        if (!empty($this->alternativeRepresentations)) {
            $prepared = array_merge($prepared, $this->alternativeRepresentations);
        }

        return $prepared;
    }
}
