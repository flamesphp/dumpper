<?php

namespace Flames\Dumpper\Parsers;

use Exception;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * XML parser — currently a no-op (unsolved parsing problem).
 *
 * @internal
 */
class DumpParsersXml implements DumpParserInterface
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
        return false;
    }
}
