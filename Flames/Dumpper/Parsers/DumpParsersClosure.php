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
        if (!$variable instanceof Closure) {
            return false;
        }

        $varData->type = 'Closure';
        $reflection    = new ReflectionFunction($variable);

        $parameters = array();
        foreach ($reflection->getParameters() as $parameter) {
            $parameters = $parameter->name;
        }
        if (!empty($parameters)) {
            $varData->addTabToView($variable, 'Closure Parameters', $parameters);
        }

        $uses = array();
        if ($val = $reflection->getStaticVariables()) {
            $uses = $val;
        }
        if (method_exists($reflection, 'getClosureThis') && $val = $reflection->getClosureThis()) {
            $uses[] = DumpParser::process($val, 'Closure $this');
        }
        if (!empty($uses)) {
            $varData->addTabToView($variable, 'Closure uses', $uses);
        }

        if ($reflection->getFileName()) {
            $varData->value = DumpHelper::ideLink($reflection->getFileName(), $reflection->getStartLine());
        }
    }
}
