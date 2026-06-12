<?php
declare(strict_types=1);


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
    public function replacesAllOtherParsers(): bool
    {
        return true;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!is_a($variable, '\Illuminate\Database\Eloquent\Model')) {
            return false;
        }

        $reflection     = new ReflectionObject($variable);
        $attrReflection = $reflection->getProperty('attributes');
        $attributes     = $attrReflection->getValue($variable);

        $reference = '`' . $variable->getConnection()->getDatabaseName() . '`.`' . $variable->getTable() . '`';

        $varData->size = count($attributes);
        if (DumpHelper::isRichMode()) {
            $varData->type = $reflection->getName();
            $varData->addTabToView($variable, 'data from ' . $reference, $attributes);
        } else {
            $varData->type          = $reflection->getName() . '; ' . $reference . ' row data:';
            $varData->extendedValue = DumpParser::alternativesParse($variable, $attributes);
        }

        return null;
    }
}
