<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Flames\Dumpper\Dump;

/**
 * Skips objects whose class name matches Dump::$classNameBlacklist.
 *
 * Runs after the "replacesAllOtherParsers" group so that explicitly dumped
 * top-level objects are never silently suppressed.
 *
 * @internal
 */
class DumpParsersBlacklist implements DumpParserInterface
{
    /** @return bool */
    public function replacesAllOtherParsers()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(&$variable, $varData)
    {
        if (DumpParser::$_level === 1) {
            return false;
        }
        if (!is_object($variable)) {
            return false;
        }

        $className = get_class($variable);
        $match     = false;
        foreach (Dump::$classNameBlacklist as $item) {
            if (preg_match($item, $className)) {
                $match = true;
                break;
            }
        }

        if (!$match) {
            return false;
        }

        $varData->type = get_class($variable) . ' [skipped]';
    }
}
