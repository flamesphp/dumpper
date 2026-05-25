<?php

namespace Flames\Dumpper\Parsers;

use DateTimeInterface;
use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * Formats DateTimeInterface instances as a human-readable timestamp string.
 *
 * @internal
 */
class DumpParsersDateTime implements DumpParserInterface
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
        if (!$variable instanceof DateTimeInterface) {
            return false;
        }

        $format = 'Y-m-d H:i:s';
        $ms     = $variable->format('u');

        if (rtrim($ms, '0')) {
            $format .= '.' . $ms;
        } else {
            $format .= '.0';
        }

        if ($variable->getTimezone()->getLocation()) {
            $format .= ' e';
        }
        $format .= ' (P)';

        $varData->value = $variable->format($format);
        $varData->type  = get_class($variable);
    }
}
