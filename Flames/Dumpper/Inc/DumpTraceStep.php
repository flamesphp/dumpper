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
    public ?string $functionName  = null;
    public bool $isBlackListed    = false;
    public ?string $fileLine      = null;
    public ?string $sourceSnippet = null;
    public array $arguments       = [];
    public array $argumentNames   = [];
    public ?DumpVariableData $object = null;

    /**
     * @param array $step       a single entry from debug_backtrace()
     * @param int   $stepNumber 0-based position in the trace
     */
    public function __construct(array $step, int $stepNumber)
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

    private function isStepBlacklisted(array $step, int $stepNumber): bool
    {
        if (!Dump::$maxLevels || !isset($step['file'])) {
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

    private function getFileAndLine(array $step): string
    {
        if (!isset($step['file'])) {
            return 'PHP internal call';
        }

        return DumpHelper::ideLink($step['file'], $step['line']);
    }

    private function getStepArgumentNames(array $step): array
    {
        if (empty($step['args']) || empty($step['function'])) {
            return [];
        }

        $function = $step['function'];
        if (in_array($function, ['include', 'include_once', 'require', 'require_once'], true)) {
            return ['<file>'];
        }

        $reflection = null;
        if (isset($step['class'])) {
            if (method_exists($step['class'], $function)) {
                $reflection = new ReflectionMethod($step['class'], $function);
            }
        } elseif (function_exists($function)) {
            $reflection = new ReflectionFunction($function);
        }

        $params = $reflection?->getParameters();
        $names  = [];

        foreach ($step['args'] as $i => $arg) {
            $names[] = isset($params[$i]) ? '$' . $params[$i]->name : '#' . ($i + 1);
        }

        return $names;
    }

    private function getStepFunctionName(array $step, array $functionNames): string
    {
        if (empty($step['function'])) {
            return '';
        }

        $function = $step['function'];
        if (isset($step['class'])) {
            $function = $step['class'] . $step['type'] . $function;
        }

        return $function . '(' . implode(', ', $functionNames) . ')';
    }

    private function getObject(array $step): ?DumpVariableData
    {
        if (!isset($step['object'])) {
            return null;
        }

        return DumpParser::process($step['object']);
    }

    private function getSourceSnippet(array $step): ?string
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
        $range       = ['start' => $line - 7, 'end' => $line + 7];
        $format      = '% ' . strlen($range['end']) . 'd';
        $source      = '';

        while (($row = fgets($file)) !== false) {
            if (++$readingLine > $range['end']) {
                break;
            }
            if ($readingLine >= $range['start']) {
                $row = DumpHelper::esc($row);
                $row = '<span>' . sprintf($format, $readingLine) . '</span> ' . $row;
                $row = $readingLine === (int)$line
                    ? '<div class="_dumpper-highlight">' . $row . '</div>'
                    : '<div>' . $row . '</div>';
                $source .= $row;
            }
        }

        fclose($file);

        return $source;
    }

    private function getArguments(array $step, array $argumentNames): array
    {
        $result = [];
        foreach ($this->getRawArguments($step) as $k => $variable) {
            $name   = $argumentNames[$k] ?? '';
            $parsed = DumpParser::process($variable, $argumentNames[$k] ?? null);
            $parsed->operator = str_starts_with($name, '$') ? '=' : ':';
            $result[] = $parsed;
        }

        return $result;
    }

    private function getRawArguments(array $step): array
    {
        if (
            !empty($step['args'])
            && in_array($step['function'], ['include', 'include_once', 'require', 'require_once'], true)
        ) {
            return [DumpHelper::shortenPath($step['args'][0])];
        }

        return $step['args'] ?? [];
    }
}
