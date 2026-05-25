<?php

namespace Flames\Dumpper\Inc;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Inc\DumpVariableData;
use Flames\Dumpper\Dump;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Represents a single step in a debug backtrace, with source snippet and argument inspection.
 *
 * @internal
 */
class DumpTraceStep
{
    public $functionName    = null;
    public $isBlackListed   = false;
    public $fileLine        = null;
    public $sourceSnippet   = null;
    public $arguments       = array();
    public $argumentNames   = array();
    /** @var DumpVariableData|null */
    public $object          = null;

    /**
     * @param array $step        a single entry from debug_backtrace()
     * @param int   $stepNumber  0-based position in the trace
     */
    public function __construct($step, $stepNumber)
    {
        $this->fileLine      = $this->getFileAndLine($step);
        $this->argumentNames = $this->getStepArgumentNames($step);
        $this->functionName  = $this->getStepFunctionName($step, $this->argumentNames);

        if ($this->isStepBlacklisted($step, $stepNumber)) {
            $this->isBlackListed = true;
            return;
        }

        $this->object        = $this->getObject($step);
        $this->sourceSnippet = $this->getSourceSnippet($step);
        $this->arguments     = $this->getArguments($step, $this->argumentNames);
    }

    /**
     * @param array $step
     * @param int   $stepNumber
     * @return bool
     */
    private function isStepBlacklisted($step, $stepNumber)
    {
        if (!Dump::$maxLevels) {
            return false;
        }
        if (!isset($step['file'])) {
            return false;
        }
        if ($stepNumber < Dump::$minimumTraceStepsToShowFull) {
            return false;
        }

        foreach (Dump::$traceBlacklist as $blacklistedPath) {
            if (preg_match($blacklistedPath, $step['file'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $step
     * @return string
     */
    private function getFileAndLine($step)
    {
        if (!isset($step['file'])) {
            return 'PHP internal call';
        }

        return DumpHelper::ideLink($step['file'], $step['line']);
    }

    /**
     * @param array $step
     * @return array
     */
    private function getStepArgumentNames($step)
    {
        if (empty($step['args']) || empty($step['function'])) {
            return array();
        }

        $function = $step['function'];
        if (in_array($function, array('include', 'include_once', 'require', 'require_once'))) {
            return array('<file>');
        }

        $reflection = null;
        if (isset($step['class'])) {
            if (method_exists($step['class'], $function)) {
                $reflection = new ReflectionMethod($step['class'], $function);
            }
        } elseif (function_exists($function)) {
            $reflection = new ReflectionFunction($function);
        }

        $params = $reflection ? $reflection->getParameters() : null;

        $names = array();
        foreach ($step['args'] as $i => $arg) {
            if (isset($params[$i])) {
                $names[] = '$' . $params[$i]->name;
            } else {
                $names[] = '#' . ($i + 1);
            }
        }

        return $names;
    }

    /**
     * @param array $step
     * @param array $functionNames
     * @return string
     */
    private function getStepFunctionName($step, $functionNames)
    {
        if (empty($step['function'])) {
            return '';
        }

        $function = $step['function'];
        if ($function && isset($step['class'])) {
            $function = $step['class'] . $step['type'] . $function;
        }

        return $function . '(' . implode(', ', $functionNames) . ')';
    }

    /**
     * @param array $step
     * @return DumpVariableData|null
     */
    private function getObject($step)
    {
        if (!isset($step['object'])) {
            return null;
        }

        return DumpParser::process($step['object']);
    }

    /**
     * @param array $step
     * @return string|null
     */
    private function getSourceSnippet($step)
    {
        if (
            empty($step['file'])
            || !isset($step['line'])
            || Dump::enabled() !== Dump::MODE_RICH
            || !is_readable($step['file'])
        ) {
            return null;
        }

        $file        = fopen($step['file'], 'r');
        $line        = $step['line'];
        $readingLine = 0;
        $range       = array('start' => $line - 7, 'end' => $line + 7);
        $format      = '% ' . strlen($range['end']) . 'd';
        $source      = '';

        while (($row = fgets($file)) !== false) {
            if (++$readingLine > $range['end']) {
                break;
            }
            if ($readingLine >= $range['start']) {
                $row = DumpHelper::esc($row);
                $row = '<span>' . sprintf($format, $readingLine) . '</span> ' . $row;
                if ($readingLine === (int)$line) {
                    $row = '<div class="_dumpper-highlight">' . $row . '</div>';
                } else {
                    $row = '<div>' . $row . '</div>';
                }
                $source .= $row;
            }
        }

        fclose($file);

        return $source;
    }

    /**
     * @param array $step
     * @param array $argumentNames
     * @return DumpVariableData[]
     */
    private function getArguments($step, $argumentNames)
    {
        $result = array();
        foreach ($this->getRawArguments($step) as $k => $variable) {
            $name   = isset($argumentNames[$k]) ? $argumentNames[$k] : '';
            $parsed = DumpParser::process($variable, $argumentNames[$k]);
            $parsed->operator = substr($name, 0, 1) === '$' ? '=' : ':';
            $result[] = $parsed;
        }

        return $result;
    }

    /**
     * @param array $step
     * @return array
     */
    private function getRawArguments($step)
    {
        if (
            !empty($step['args'])
            && in_array($step['function'], array('include', 'include_once', 'require', 'require_once'), true)
        ) {
            return array(DumpHelper::shortenPath($step['args'][0]));
        }

        return isset($step['args']) ? $step['args'] : array();
    }
}
