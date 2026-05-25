<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Traversable;

/**
 * Exposes the iterator contents of any Traversable object as an extra tab.
 *
 * @internal
 */
class DumpParsersObjectIterateable implements DumpParserInterface
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
        if (
            !DumpHelper::isRichMode()
            || !DumpHelper::php53orLater()
            || !is_object($variable)
            || !$variable instanceof Traversable
            || stripos($class = get_class($variable), 'zend') !== false
            || strpos($class, 'DOMN') !== 0
        ) {
            return false;
        }

        $arrayCopy = iterator_to_array($variable, true);
        $size      = count($arrayCopy);

        $varData->addTabToView($variable, "Iterator contents ({$size})", $arrayCopy);
    }
}
