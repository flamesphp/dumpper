<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Exception;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;
use Flames\Dumpper\Dump;
use ReflectionClass;
use Throwable;

/**
 * Detects strings that are valid, user-defined class names and links to their source file.
 *
 * @internal
 */
class DumpParsersClassName implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (
            Dump::enabled() === Dump::MODE_TEXT_ONLY
            || empty($variable)
            || !is_string($variable)
            || strlen($variable) < 3
        ) {
            return false;
        }

        try {
            if (!@class_exists($variable)) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        $reflector = new ReflectionClass($variable);
        if (!$reflector->isUserDefined()) {
            return false;
        }

        if (DumpHelper::isRichMode()) {
            $varData->addTabToView(
                $variable,
                'Existing class',
                DumpHelper::ideLink(
                    $reflector->getFileName(),
                    $reflector->getStartLine(),
                    $reflector->getShortName()
                )
            );
        } else {
            $varData->extendedValue = DumpHelper::isHtmlMode()
                ? ['Existing class' => DumpHelper::ideLink($reflector->getFileName(), $reflector->getStartLine(), $reflector->getShortName())]
                : ['Existing class' => $reflector->getFileName() . ':' . $reflector->getStartLine()];
        }

        return null;
    }
}
