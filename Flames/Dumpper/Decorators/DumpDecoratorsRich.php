<?php

namespace Flames\Dumpper\Decorators;

use Flames\Dumpper\Decorators\DumpDecoratorsInterface;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Inc\DumpTraceStep;
use Flames\Dumpper\Inc\DumpVariableData;
use Flames\Dumpper\Dump;

/**
 * Rich HTML decorator — renders interactive, collapsible dump output with CSS/JS assets.
 *
 * @internal
 */
class DumpDecoratorsRich implements DumpDecoratorsInterface
{
    protected static bool $needsAssets = true;

    private static ?string $cachedInit = null;

    public function areAssetsNeeded(): bool
    {
        return self::$needsAssets;
    }

    public function setAssetsNeeded(bool $added): void
    {
        self::$needsAssets = $added;
    }

    public function decorate(DumpVariableData $varData): string
    {
        $output = '<dl>';

        $allRepresentations = $varData->getAllRepresentations();
        $extendedPresent    = !empty($allRepresentations);

        if ($extendedPresent) {
            $class = '_dumpper-parent';
            if (Dump::$expandedByDefault) {
                $class .= ' _dumpper-show';
            }
            $output .= '<dt class="' . $class . '">';
        } else {
            $output .= '<dt>';
        }

        if ($extendedPresent) {
            $output .= '<nav></nav>';
        }

        $output .= $this->_drawHeader($varData) . $varData->value . '</dt>';

        if ($extendedPresent) {
            $output .= '<dd>';
        }

        if (count($allRepresentations) === 1 && !empty($varData->extendedValue)) {
            $output .= $this->decorateAlternativeView(reset($allRepresentations));
        } elseif ($extendedPresent) {
            $output .= '<ul class="_dumpper-tabs">';

            $isFirst = true;
            foreach ($allRepresentations as $tabName => $_) {
                $active  = $isFirst ? ' class="_dumpper-active-tab"' : '';
                $isFirst = false;
                $output .= "<li{$active}>" . DumpHelper::esc($tabName) . '</li>';
            }

            $output .= '</ul><ul>';

            foreach ($allRepresentations as $alternative) {
                $output .= '<li>';
                $output .= $this->decorateAlternativeView($alternative);
                $output .= '</li>';
            }

            $output .= '</ul>';
        }
        if ($extendedPresent) {
            $output .= '</dd>';
        }

        $output .= "</dl>\n";

        return $output;
    }

    /**
     * @param DumpTraceStep[] $traceData
     */
    public function decorateTrace(array $traceData, bool $pathsOnly = false): string
    {
        $output = '<dl class="_dumpper-trace">';

        $blacklistedStepsInARow = 0;
        foreach ($traceData as $i => $step) {
            if ($step->isBlackListed) {
                $blacklistedStepsInARow++;
                continue;
            }

            if ($blacklistedStepsInARow) {
                if ($blacklistedStepsInARow <= 5) {
                    for ($j = $blacklistedStepsInARow; $j > 0; $j--) {
                        $output .= $this->drawTraceStep($i - $j, $traceData[$i - $j], $pathsOnly);
                    }
                } else {
                    $output .= "<dt><b></b>[{$blacklistedStepsInARow} steps skipped]</dt>";
                }
                $blacklistedStepsInARow = 0;
            }

            $output .= $this->drawTraceStep($i, $step, $pathsOnly);
        }

        if ($blacklistedStepsInARow > 1) {
            $output .= "<dt><b></b>[{$blacklistedStepsInARow} steps skipped]</dt>";
        }

        $output .= '</dl>';

        return $output;
    }

    private function drawTraceStep(int $i, DumpTraceStep $step, bool $pathsOnly): string
    {
        $isChildless = !$step->sourceSnippet && !$step->arguments && !$step->object;

        if ($step->isBlackListed) {
            $class = ' _dumpper-blacklisted';
        } elseif ($isChildless) {
            $class = ' _dumpper-childless';
        } else {
            $class = '_dumpper-parent';
            if (Dump::$expandedByDefault) {
                $class .= ' _dumpper-show';
            }
        }

        $output  = '<dt class="' . $class . '">';
        $output .= '<b>' . ($i + 1) . '</b>';
        if (!$isChildless) {
            $output .= '<nav></nav>';
        }
        $output .= '<var>' . $step->fileLine . '</var> ';
        $output .= $step->functionName;
        $output .= '</dt>';

        if ($isChildless) {
            return $output;
        }

        $output       .= '<dd><ul class="_dumpper-tabs">';
        $firstTabClass = ' class="_dumpper-active-tab"';

        if ($step->sourceSnippet) {
            $output       .= "<li{$firstTabClass}>Source</li>";
            $firstTabClass = '';
        }
        if (!$pathsOnly && $step->arguments) {
            $output       .= "<li{$firstTabClass}>Arguments</li>";
            $firstTabClass = '';
        }
        if (!$pathsOnly && $step->object) {
            $output .= "<li{$firstTabClass}>Callee object [{$step->object->type}]</li>";
        }

        $output .= '</ul><ul>';

        if ($step->sourceSnippet) {
            $output .= "<li><pre class=\"_dumpper-source\">{$step->sourceSnippet}</pre></li>";
        }
        if (!$pathsOnly && $step->arguments) {
            $output .= '<li>';
            foreach ($step->arguments as $argument) {
                $output .= $this->decorate($argument);
            }
            $output .= '</li>';
        }
        if (!$pathsOnly && $step->object) {
            $output .= '<li>' . $this->decorate($step->object) . '</li>';
        }

        $output .= '</ul></dd>';

        return $output;
    }

    public function wrapStart(): string
    {
        return '<div class="_dumpper">'
            . '<button type="button" class="_dumpper-close" title="Close dump" aria-label="Close dump">&times;</button>';
    }

    public function wrapEnd(array $callee, array $miniTrace, array $prevCaller): string
    {
        if (!Dump::$displayCalledFrom) {
            return '</div>';
        }

        $callingFunction = '';
        $calleeInfo      = '';
        $traceDisplay    = '';

        if (isset($prevCaller['class'])) {
            $callingFunction = $prevCaller['class'];
        }
        if (isset($prevCaller['type'])) {
            $callingFunction .= $prevCaller['type'];
        }
        if (
            isset($prevCaller['function'])
            && !in_array($prevCaller['function'], ['include', 'include_once', 'require', 'require_once'], true)
        ) {
            $callingFunction .= $prevCaller['function'] . '()';
        }
        if ($callingFunction) {
            $callingFunction = " [{$callingFunction}]";
        }

        if (isset($callee['file'])) {
            $calleeInfo .= 'Called from ' . DumpHelper::ideLink(
                $callee['file'],
                $callee['line'] ?? null,
                DumpHelper::traceLinkText($callee['file'], $callee['line'] ?? null)
            );
        }

        if (!empty($miniTrace)) {
            $traceDisplay = '<ol>';
            foreach ($miniTrace as $step) {
                $traceDisplay .= '<li>' . DumpHelper::ideLink(
                    $step['file'],
                    $step['line'],
                    DumpHelper::traceLinkText($step['file'], $step['line'] ?? null)
                );
                if (
                    isset($step['function'])
                    && !in_array($step['function'], ['include', 'include_once', 'require', 'require_once'], true)
                ) {
                    $classString   = ' [';
                    $classString  .= $step['class'] ?? '';
                    $classString  .= $step['type'] ?? '';
                    $classString  .= $step['function'] . '()]';
                    $traceDisplay .= $classString;
                }
            }
            $traceDisplay .= '</ol>';
            $calleeInfo    = '<nav></nav>' . $calleeInfo;
        }

        $callingFunction .= ' @ ' . date('Y-m-d H:i:s');

        return '<footer>'
            . "{$calleeInfo}{$callingFunction}{$traceDisplay}"
            . '</footer></div>';
    }

    private function _drawHeader(DumpVariableData $varData): string
    {
        $output = '';
        if ($varData->access !== null) {
            $output .= "<var>{$varData->access}</var> ";
        }
        if ($varData->name !== null && $varData->name !== '') {
            $output .= '<dfn>' . DumpHelper::esc($varData->name) . '</dfn> ';
        }
        if ($varData->operator !== null) {
            $output .= $varData->operator . ' ';
        }
        if ($varData->type !== null) {
            $output .= "<var>{$varData->type}</var> ";
        }
        if ($varData->size !== null) {
            $output .= '(' . $varData->size . ') ';
        }

        return $output;
    }

    /**
     * Produces the inline CSS and JS assets needed for rich display (cached after first load).
     */
    public function init(): string
    {
        if (self::$cachedInit !== null) {
            return self::$cachedInit;
        }

        $baseDir = Dump::dir() . '../../resources/';

        $defaultCss = (string)file_get_contents($baseDir . 'material-kit.css');

        $mountHtml =
            '<script class="_dumpper-js">' . file_get_contents($baseDir . 'dumpper.js') . '</script>'
            . '<style class="_dumpper-css">' . $defaultCss . '</style>';

        $darkCss = $baseDir . 'dark.css';
        if (file_exists($darkCss)) {
            $mountHtml .= '<style class="_dumpper-css">' . file_get_contents($darkCss) . '</style>';
        }

        $mountHtml .= "\n";

        return self::$cachedInit = $mountHtml;
    }

    private function decorateAlternativeView(mixed $alternative): string
    {
        if (empty($alternative)) {
            return '';
        }

        $output = '';
        if (is_array($alternative)) {
            $parse = reset($alternative) instanceof DumpVariableData
                ? $alternative
                : DumpParser::process($alternative)->extendedValue;

            foreach ($parse as $v) {
                $output .= $this->decorate($v);
            }
        } elseif (is_string($alternative)) {
            if ($alternative[0] === "\n" || $alternative[0] === "\r") {
                $alternative = "\n" . $alternative;
            }
            $output .= "<pre>{$alternative}</pre>";
        }

        return $output;
    }
}
