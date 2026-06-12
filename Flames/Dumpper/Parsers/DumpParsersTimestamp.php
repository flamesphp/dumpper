<?php
declare(strict_types=1);


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
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!$this->_fits($variable)) {
            return false;
        }

        $var = strlen((string)$variable) === 13 ? substr((string)$variable, 0, -3) : $variable;

        $varData->addTabToView($variable, 'Timestamp', @date('Y-m-d H:i:s', (int)$var));
        return null;
    }

    private function _fits(mixed $variable): bool
    {
        if (!DumpHelper::isRichMode()) {
            return false;
        }
        if (!is_string($variable) && !is_int($variable)) {
            return false;
        }

        $len = strlen((string)(int)$variable);

        return (
            $len === 9 || $len === 10
            || ($len === 13 && str_ends_with((string)$variable, '000'))
        )
        && ((string)(int)$variable == $variable);
    }
}
