<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Flames\Dumpper\Parsers\DumpParsersSplFileInfo;
use SplFileInfo;

/**
 * Detects string values that look like readable file-system paths.
 *
 * @internal
 */
class DumpParsersFilePath extends DumpParsersSplFileInfo implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (
            !is_string($variable)
            || ($strlen = strlen($variable)) > 2048
            || $strlen < 3
            || !preg_match('#[\\\\/]#', $variable)
            || preg_match('/[?<>"*|]/', $variable)
            || !@is_readable($variable)
        ) {
            return false;
        }

        return $this->run($variable, $varData, new SplFileInfo($variable));
    }
}
