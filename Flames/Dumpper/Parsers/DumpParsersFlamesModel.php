<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Flames\Orm\Model;

/**
 * Extracts model data and metadata from Flames\Orm\Model instances.
 *
 * @internal
 */
class DumpParsersFlamesModel implements DumpParserInterface
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
            || !$variable instanceof Model
        ) {
            return false;
        }

        $arrayCopy = $variable->toArray();
        $size      = count($arrayCopy);

        $modelDetails = [
            'database' => $variable::getDatabase(),
            'table'    => $variable::getTable(),
        ];

        $varData->addTabToView($variable, "Model contents ({$size})", $arrayCopy);
        $varData->addTabToView($variable, 'Model details', $modelDetails);

        return null;
    }
}
