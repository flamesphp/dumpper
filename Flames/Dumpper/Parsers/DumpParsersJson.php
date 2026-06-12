<?php
declare(strict_types=1);


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
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (
            !DumpHelper::isRichMode()
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
        return null;
    }
}
