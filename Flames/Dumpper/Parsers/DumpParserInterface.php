<?php
declare(strict_types=1);


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
     */
    public function replacesAllOtherParsers(): bool;

    /**
     * Attempts to parse $variable and populate $varData.
     *
     * @return false|void  false = did not handle (try next parser), void = handled
     */
    public function parse(mixed &$variable, mixed $varData): mixed;
}
