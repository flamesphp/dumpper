<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;
use ReflectionObject;

/**
 * Extracts row data and table reference from Eloquent Model instances.
 *
 * @internal
 */
class DumpParsersEloquent implements DumpParserInterface
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
        if (!DumpHelper::php53orLater() || !is_a($variable, '\Illuminate\Database\Eloquent\Model')) {
            return false;
        }

        $reflection   = new ReflectionObject($variable);
        $attrReflection = $reflection->getProperty('attributes');
        $attrReflection->setAccessible(true);
        $attributes = $attrReflection->getValue($variable);

        $reference = '`' . $variable->getConnection()->getDatabaseName() . '`.`' . $variable->getTable() . '`';

        $varData->size = count($attributes);
        if (DumpHelper::isRichMode()) {
            $varData->type = $reflection->getName();
            $varData->addTabToView($variable, 'data from ' . $reference, $attributes);
        } else {
            $varData->type          = $reflection->getName() . '; ' . $reference . ' row data:';
            $varData->extendedValue = DumpParser::alternativesParse($variable, $attributes);
        }
    }
}
