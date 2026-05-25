<?php

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
            Dump::enabled() === Dump::MODE_TEXT_ONLY
            || !DumpHelper::php53orLater()
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
        } catch (Throwable $t) {
            return false;
        } catch (Exception $e) {
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
            if (DumpHelper::isHtmlMode()) {
                $varData->extendedValue = array(
                    'Existing class' => DumpHelper::ideLink(
                        $reflector->getFileName(),
                        $reflector->getStartLine(),
                        $reflector->getShortName()
                    ),
                );
            } else {
                $varData->extendedValue = array(
                    'Existing class' => $reflector->getFileName() . ':' . $reflector->getStartLine(),
                );
            }
        }
    }
}
