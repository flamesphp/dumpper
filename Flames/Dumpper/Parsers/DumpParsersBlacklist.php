<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Flames\Dumpper\Dump;

/**
 * Skips objects whose class name matches Dump::$classNameBlacklist.
 *
 * @internal
 */
class DumpParsersBlacklist implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return true;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (DumpParser::$_level === 1 || !is_object($variable)) {
            return false;
        }

        $className = get_class($variable);
        foreach (Dump::$classNameBlacklist as $item) {
            if (preg_match($item, $className)) {
                $varData->type = $className . ' [skipped]';
                return null;
            }
        }

        return false;
    }
}
