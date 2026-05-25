<?php

namespace Flames\Dumpper\Parsers;

use Closure;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Parsers\DumpParserInterface;
use ReflectionFunction;

/**
 * Provides source location, parameter names, and captured variables for Closure instances.
 *
 * @internal
 * @noinspection AutoloadingIssuesInspection
 */
class DumpParsersClosure implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return true;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!$variable instanceof Closure) {
            return false;
        }

        $varData->type = 'Closure';
        $reflection    = new ReflectionFunction($variable);

        $parameters = [];
        foreach ($reflection->getParameters() as $parameter) {
            $parameters[] = $parameter->name;
        }
        if (!empty($parameters)) {
            $varData->addTabToView($variable, 'Closure Parameters', $parameters);
        }

        $uses = [];
        if ($val = $reflection->getStaticVariables()) {
            $uses = $val;
        }
        if ($val = $reflection->getClosureThis()) {
            $uses[] = DumpParser::process($val, 'Closure $this');
        }
        if (!empty($uses)) {
            $varData->addTabToView($variable, 'Closure uses', $uses);
        }

        if ($reflection->getFileName()) {
            $varData->value = DumpHelper::ideLink($reflection->getFileName(), $reflection->getStartLine());
        }

        return null;
    }
}
