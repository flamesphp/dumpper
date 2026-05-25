<?php

namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;
use ReflectionClass;
use ReflectionProperty;

/**
 * Appends a "Static class properties" tab for any object that has statics.
 *
 * @internal
 */
class DumpParsersClassStatics implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!DumpHelper::isRichMode() || !is_object($variable)) {
            return false;
        }

        $statics    = [];
        $reflection = new ReflectionClass(get_class($variable));

        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if ($property->isProtected()) {
                $access = 'protected';
            } elseif ($property->isPrivate()) {
                $access = 'private';
            } else {
                $access = 'public';
            }

            if (!$property->isInitialized($variable)) {
                $value   = null;
                $access .= ' [uninitialized]';
            } else {
                $value = $property->getValue($variable);
            }

            $name   = '$' . $property->getName();
            $output = DumpParser::process($value, DumpHelper::esc($name));

            $output->access   = $access;
            $output->operator = '::';
            $statics[] = $output;
        }

        if (empty($statics)) {
            return false;
        }

        $varData->addTabToView(
            $variable,
            'Static class properties (' . count($statics) . ')',
            $statics
        );

        return null;
    }
}
