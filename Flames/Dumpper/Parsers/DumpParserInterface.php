<?php

namespace Flames\Dumpper\Parsers;

/**
 * Contract for all variable parsers that augment DumpVariableData.
 *
 * @internal
 */
interface DumpParserInterface
{
    /**
     * Whether a successful parse stops all subsequent parsers from running.
     *
     * @return bool
     */
    public function replacesAllOtherParsers();

    /**
     * Attempts to parse $variable and populate $varData.
     *
     * @param mixed $variable
     * @param mixed $varData  DumpVariableData instance
     * @return false|void  false = did not handle (try next parser), void = handled
     */
    public function parse(&$variable, $varData);
}
