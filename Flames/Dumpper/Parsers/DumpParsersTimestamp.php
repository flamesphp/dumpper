<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * Detects Unix timestamp integers/strings and shows a formatted date.
 * Also handles JavaScript microsecond timestamps (13-digit ending in '000').
 *
 * @internal
 */
class DumpParsersTimestamp implements DumpParserInterface
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
        if (!$this->_fits($variable)) {
            return false;
        }

        $var = strlen($variable) === 13 ? substr($variable, 0, -3) : $variable;

        $varData->addTabToView($variable, 'Timestamp', @date('Y-m-d H:i:s', $var));
    }

    /**
     * @param mixed $variable
     * @return bool
     */
    private function _fits($variable)
    {
        if (!DumpHelper::isRichMode()) {
            return false;
        }
        if (!is_string($variable) && !is_int($variable)) {
            return false;
        }

        $len = strlen((int)$variable);

        return (
            $len === 9 || $len === 10
            || ($len === 13 && substr($variable, -3) === '000')
        )
        && ((string)(int)$variable == $variable);
    }
}
