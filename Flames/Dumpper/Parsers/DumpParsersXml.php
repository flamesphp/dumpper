<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * XML parser — currently a no-op (unsolved parsing problem).
 *
 * @internal
 */
class DumpParsersXml implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        return false;
    }
}
