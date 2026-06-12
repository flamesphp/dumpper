<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Parsers\DumpParserInterface;
use Smarty;

/**
 * Displays Smarty template engine assigned variables and configuration.
 *
 * @internal
 */
class DumpParsersSmarty implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return true;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!$variable instanceof Smarty || !defined('Smarty::SMARTY_VERSION')) {
            return false;
        }

        $varData->name = 'Smarty v' . Smarty::SMARTY_VERSION;

        $assigned      = [];
        $globalAssigns = [];

        foreach ($variable->tpl_vars as $name => $var) {
            $assigned[$name] = $var->value;
        }
        foreach (Smarty::$global_tpl_vars as $name => $var) {
            if ($name === 'SCRIPT_NAME') {
                continue;
            }
            $globalAssigns[$name] = $var->value;
        }

        $varData->addTabToView($variable, 'Assigned to view', $assigned);
        $varData->addTabToView($variable, 'Assigned globally', $globalAssigns);
        $varData->addTabToView($variable, 'Configuration', [
            'Compiled files stored in' => isset($variable->compile_dir)
                ? $variable->compile_dir
                : $variable->getCompileDir(),
        ]);

        return null;
    }
}
