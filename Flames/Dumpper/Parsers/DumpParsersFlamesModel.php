<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Flames\Model;

/**
 * Extracts model data and metadata from Flames\Model instances.
 *
 * @internal
 */
class DumpParsersFlamesModel implements DumpParserInterface
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
            || !$variable instanceof Model
        ) {
            return false;
        }

        $arrayCopy = $variable->toArray();
        $size      = count($arrayCopy);

        $modelDetails = array(
            'database' => $variable::getDatabase(),
            'table'    => $variable::getTable(),
        );

        $varData->addTabToView($variable, "Model contents ({$size})", $arrayCopy);
        $varData->addTabToView($variable, 'Model details', $modelDetails);
    }
}
