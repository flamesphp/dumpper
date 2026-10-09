<?php
declare(strict_types=1);

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * Renders Flames\Collection\Arr and ArrImmutable as native PHP arrays in dumps.
 *
 * @internal
 */
class DumpParsersFlamesCollection implements DumpParserInterface
{
    private const string COLLECTION_ARR = 'Flames\Collection\Arr';

    public function replacesAllOtherParsers(): bool
    {
        return true;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (
            !is_object($variable)
            || !class_exists(self::COLLECTION_ARR, false)
            || !is_a($variable, self::COLLECTION_ARR)
        ) {
            return false;
        }

        $arrayCopy = $variable->toArray();

        $varData->type          = 'array';
        $varData->size          = count($arrayCopy);
        $varData->extendedValue = DumpParser::alternativesParse($variable, $arrayCopy);

        return null;
    }
}
