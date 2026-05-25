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
    protected static $needsAssets = true;

    /** @return bool */
    public function areAssetsNeeded()
    {
        return self::$needsAssets;
    }

    /** @param bool $added */
    public function setAssetsNeeded($added)
    {
        self::$needsAssets = $added;
    }

    /**
     * {@inheritdoc}
     */
    public function decorate(DumpVariableData $varData)
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
            $output .= '<span class="_dumpper-popup-trigger">&rarr;</span><nav></nav>';
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
                $active   = $isFirst ? ' class="_dumpper-active-tab"' : '';
                $isFirst  = false;
                $output  .= "<li{$active}>" . DumpHelper::esc($tabName) . '</li>';
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
     * {@inheritdoc}
     *
     * @param DumpTraceStep[] $traceData
     */
    public function decorateTrace(array $traceData, $pathsOnly = false)
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

    /**
     * @param int           $i
     * @param DumpTraceStep $step
     * @param bool          $pathsOnly
     * @return string
     */
    private function drawTraceStep($i, $step, $pathsOnly)
    {
        $isChildless = !$step->sourceSnippet && !$step->arguments && !$step->object;

        $class = '';
        if ($step->isBlackListed) {
            $class .= ' _dumpper-blacklisted';
        } elseif ($isChildless) {
            $class .= ' _dumpper-childless';
        } else {
            $class .= '_dumpper-parent';
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

        $output .= '<dd><ul class="_dumpper-tabs">';
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

    /**
     * {@inheritdoc}
     */
    public function wrapStart()
    {
        return '<div class="_dumpper">';
    }

    /**
     * {@inheritdoc}
     */
    public function wrapEnd($callee, $miniTrace, $prevCaller)
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
            && !in_array($prevCaller['function'], array('include', 'include_once', 'require', 'require_once'))
        ) {
            $callingFunction .= $prevCaller['function'] . '()';
        }
        $callingFunction and $callingFunction = " [{$callingFunction}]";

        if (isset($callee['file'])) {
            $calleeInfo .= 'Called from ' . DumpHelper::ideLink($callee['file'], $callee['line']);
        }

        if (!empty($miniTrace)) {
            $traceDisplay = '<ol>';
            foreach ($miniTrace as $step) {
                $traceDisplay .= '<li>' . DumpHelper::ideLink($step['file'], $step['line']);
                if (
                    isset($step['function'])
                    && !in_array($step['function'], array('include', 'include_once', 'require', 'require_once'))
                ) {
                    $classString = ' [';
                    if (isset($step['class'])) {
                        $classString .= $step['class'];
                    }
                    if (isset($step['type'])) {
                        $classString .= $step['type'];
                    }
                    $classString  .= $step['function'] . '()]';
                    $traceDisplay .= $classString;
                }
            }
            $traceDisplay .= '</ol>';
            $calleeInfo    = '<nav></nav>' . $calleeInfo;
        }

        $callingFunction .= ' @ ' . date('Y-m-d H:i:s');

        return '<footer>'
            . '<span class="_dumpper-popup-trigger" title="Open in new window">&rarr;</span> '
            . "{$calleeInfo}{$callingFunction}{$traceDisplay}"
            . '</footer></div>';
    }

    /**
     * @param DumpVariableData $varData
     * @return string
     */
    private function _drawHeader(DumpVariableData $varData)
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
     * Produces the CSS and JS assets needed for rich display.
     * Only emits content once per page-load (or until `-` / `@` modifier resets the flag).
     *
     * @return string
     */
    public function init()
    {
        $baseDir = Dump::dir() . '../../resources/';

        $cssFile    = $baseDir . 'solarized-dark.css';
        $defaultCss = file_get_contents($cssFile);
        $defaultCss = str_replace('body{background:#073642;color:#fff}', '', $defaultCss);

        $mountHtml =
            '<script class="_dumpper-js">' . file_get_contents($baseDir . 'dumpper.js') . '</script>'
            . '<style class="_dumpper-css">' . $defaultCss . '</style>';

        $mountHtml .= '<style class="_dumpper-css">' . file_get_contents($baseDir . 'dark.css') . '</style>';
        $mountHtml .= "\n";

        return $mountHtml;
    }

    /**
     * @param mixed $alternative
     * @return string
     */
    private function decorateAlternativeView($alternative)
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
