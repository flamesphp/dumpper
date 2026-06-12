<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Traversable;

/**
 * Exposes the iterator contents of Traversable DOM objects as an extra tab.
 *
 * @internal
 */
class DumpParsersObjectIterateable implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (
            !DumpHelper::isRichMode()
            || !is_object($variable)
            || !$variable instanceof Traversable
            || stripos($class = get_class($variable), 'zend') !== false
            || !str_starts_with($class, 'DOMN')
        ) {
            return false;
        }

        $arrayCopy = iterator_to_array($variable, true);
        $size      = count($arrayCopy);

        $varData->addTabToView($variable, "Iterator contents ({$size})", $arrayCopy);
        return null;
    }
}
