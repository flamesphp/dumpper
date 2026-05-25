<?php

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
    /** @return bool */
    public function replacesAllOtherParsers()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(&$variable, $varData)
    {
        if (!DumpHelper::isRichMode() || !is_object($variable) || !$variable instanceof SplObjectStorage) {
            return false;
        }

        $count = $variable->count();
        if ($count === 0) {
            return false;
        }

        $variable->rewind();
        $arrayCopy = array();
        while ($variable->valid()) {
            $arrayCopy[] = $variable->current();
            $variable->next();
        }

        $varData->addTabToView($variable, "Storage contents ({$count})", $arrayCopy);
    }
}
