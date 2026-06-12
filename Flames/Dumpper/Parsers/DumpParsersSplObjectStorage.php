<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use SplObjectStorage;

/**
 * Iterates SplObjectStorage and displays its contained objects.
 *
 * @internal
 */
class DumpParsersSplObjectStorage implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!DumpHelper::isRichMode() || !$variable instanceof SplObjectStorage) {
            return false;
        }

        $count = $variable->count();
        if ($count === 0) {
            return false;
        }

        $arrayCopy = iterator_to_array($variable, false);

        $varData->addTabToView($variable, "Storage contents ({$count})", $arrayCopy);
        return null;
    }
}
