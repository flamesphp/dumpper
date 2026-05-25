<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * Detects JSON strings and adds a parsed-array tab view.
 *
 * @internal
 */
class DumpParsersJson implements DumpParserInterface
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
            || !is_string($variable)
            || !isset($variable[0])
            || ($variable[0] !== '{' && $variable[0] !== '[')
            || ($json = json_decode($variable, true)) === null
        ) {
            return false;
        }

        $val = (array)$json;
        if (empty($val)) {
            return false;
        }

        $varData->addTabToView($variable, 'Json', $val);
    }
}
