<?php

namespace Flames\Dumpper\Decorators;

use Flames\Dumpper\Decorators\DumpDecoratorsInterface;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpTraceStep;
use Flames\Dumpper\Inc\DumpVariableData;
use Flames\Dumpper\Dump;

/**
 * Plain-text / CLI decorator for dump output.
 *
 * @internal
 */
class DumpDecoratorsPlain implements DumpDecoratorsInterface
{
    protected static bool $needsAssets = true;

    private static ?bool $_enableColors = null;

    /** @var array<int, int> */
    private static array $levelColors = [];

    public function areAssetsNeeded(): bool
    {
        return self::$needsAssets;
    }

    public function setAssetsNeeded(bool $added): void
    {
        self::$needsAssets = $added;
    }

    public function decorate(DumpVariableData $varData, int $level = 0): string
    {
        $output = '';
        if ($level === 0) {
            $name          = $varData->name ? $varData->name : '';
            $varData->name = null;
            $output       .= $this->title((string)$name);
        }

        self::$levelColors = array_slice(self::$levelColors, 0, $level);
        $s     = '    ';
        $space = '';

        if (Dump::enabled() === Dump::MODE_CLI) {
            for ($i = 0; $i < $level; $i++) {
                if (!array_key_exists($i, self::$levelColors)) {
                    self::$levelColors[$i] = rand(1, 231);
                }
                $color  = self::$levelColors[$i];
                $space .= "\x1b[38;5;{$color}m┆\x1b[0m   ";
            }
        } else {
            $space = str_repeat($s, $level);
        }

        $output .= $space . $this->drawHeader($varData);

        if (isset($varData->extendedValue)) {
            $output .= ' ' . ($varData->type === 'array' ? '[' : '(') . PHP_EOL;

            if (is_array($varData->extendedValue)) {
                foreach ($varData->extendedValue as $k => $v) {
                    if (is_string($v)) {
                        $output .= $space . $s
                            . $this->colorize($k, 'key', false) . ': '
                            . $this->colorize($v, 'value');
                    } else {
                        $output .= $this->decorate($v, $level + 1);
                    }
                }
            } elseif (is_string($varData->extendedValue)) {
                $output .= $space . $s . $this->colorize($varData->extendedValue, 'value');
            }

            $output .= $space . ($varData->type === 'array' ? ']' : ')');
        }

        $output .= PHP_EOL;

        return $output;
    }

    /**
     * @param DumpTraceStep[] $traceData
     */
    public function decorateTrace(array $traceData, bool $pathsOnly = false): string
    {
        $lastStepNumber = count($traceData);
        $stepNumber     = 1;
        $output         = $this->title($pathsOnly ? 'QUICK TRACE' : 'TRACE');

        $_________________ = '────────────────────────────────────────────────────────────────────────────────';
        $____Arguments____ = '    ┌────────────────────────── Arguments ─────────────────────────────────┐';
        $__Callee_Object__ = '    ┌───────────────────────── Callee Object ──────────────────────────────┐';
        $L________________ = '    └──────────────────────────────────────────────────────────────────────┘';

        $_________________  = $this->colorize($_________________, 'header');
        $____Arguments____  = $this->colorize($____Arguments____, 'header');
        $__Callee_Object__  = $this->colorize($__Callee_Object__, 'header');
        $L________________  = $this->colorize($L________________, 'header');

        foreach ($traceData as $step) {
            $output .= str_pad($stepNumber++ . ': ', 4, ' ');
            $output .= $this->colorize($step->fileLine, 'header');

            if ($step->functionName) {
                $output .= '    ' . $step->functionName . PHP_EOL;
            }

            if (!$pathsOnly && $step->arguments) {
                $output .= $____Arguments____;
                foreach ($step->arguments as $argument) {
                    $output .= $this->decorate($argument, 2);
                }
                $output .= $L________________;
            }

            if (!$pathsOnly && $step->object) {
                $output .= $__Callee_Object__;
                $output .= $this->decorate($step->object, 2);
                $output .= $L________________;
            }

            if ($stepNumber !== $lastStepNumber) {
                $output .= $_________________;
            }
        }

        return $output;
    }

    private function colorize(mixed $text, string $type, bool $nlAfter = true): string
    {
        $text = (string)$text;
        $nl   = $nlAfter ? PHP_EOL : '';

        $mode = Dump::enabled();

        if ($mode === Dump::MODE_PLAIN) {
            if (!self::$_enableColors) {
                return $text . $nl;
            }
            $text = match ($type) {
                'key'    => "<dfn>{$text}</dfn>",
                'access' => "<i>{$text}</i>",
                'value'  => "<var>{$text}</var>",
                'type'   => "<b>{$text}</b>",
                'header' => "<h1>{$text}</h1>",
                default  => $text,
            };
            return $text . $nl;
        }

        if ($mode === Dump::MODE_CLI) {
            if (!self::$_enableColors) {
                return $text . $nl;
            }
            $ansi = match ($type) {
                'key'    => "\x1b[32m",
                'access' => "\x1b[3m",
                'header' => "\x1b[38;5;75m",
                'type'   => "\x1b[1m",
                'value'  => "\x1b[31m",
                default  => '',
            };
            return $ansi . $text . "\x1b[0m" . $nl;
        }

        return $text . $nl;
    }

    private function title(string $text): string
    {
        $escaped          = DumpHelper::esc($text);
        $lengthDifference = strlen($escaped) - strlen($text);

        $ret  = '┌──────────────────────────────────────────────────────────────────────────────┐' . PHP_EOL;
        if ($text) {
            $ret .= '│' . str_pad($escaped, 78 + $lengthDifference, ' ', STR_PAD_BOTH) . '│' . PHP_EOL;
        }
        $ret .= '└──────────────────────────────────────────────────────────────────────────────┘';

        return $this->colorize($ret, 'header');
    }

    public function wrapStart(): string
    {
        return Dump::enabled() === Dump::MODE_PLAIN ? '<pre class="_dumpper_plain">' : '';
    }

    public function wrapEnd(array $callee, array $miniTrace, array $prevCaller): string
    {
        $lastLine     = '════════════════════════════════════════════════════════════════════════════════';
        $lastChar     = Dump::enabled() === Dump::MODE_PLAIN ? '</pre>' : '';
        $traceDisplay = '';

        if (!Dump::$displayCalledFrom) {
            return $this->colorize($lastLine . $lastChar, 'header');
        }

        if (!empty($miniTrace)) {
            $traceDisplay = PHP_EOL;
            $i = 0;
            foreach ($miniTrace as $step) {
                $traceDisplay .= '        ' . $i + 2 . '. ';
                $traceDisplay .= DumpHelper::ideLink(
                    $step['file'],
                    $step['line'],
                    DumpHelper::traceLinkText($step['file'], $step['line'] ?? null)
                );
                $traceDisplay .= PHP_EOL;
                if ($i++ > 2) {
                    break;
                }
            }
        }

        return $this->colorize(
                $lastLine . PHP_EOL
                . 'Call stack ' . DumpHelper::ideLink(
                    $callee['file'],
                    $callee['line'] ?? null,
                    DumpHelper::traceLinkText($callee['file'], $callee['line'] ?? null)
                )
                . $traceDisplay,
                'header'
            )
            . $lastChar;
    }

    private function drawHeader(DumpVariableData $varData): string
    {
        $output = '';

        if ($varData->access) {
            $output .= ' ' . $this->colorize(DumpHelper::esc($varData->access), 'access', false);
        }
        if ($varData->name !== null && $varData->name !== '') {
            $output .= ' ' . $this->colorize(DumpHelper::esc($varData->name), 'key', false);
        }
        if ($varData->operator) {
            $output .= ' ' . $varData->operator;
        }

        $type = $varData->type;
        if ($varData->size !== null) {
            $type .= ' (' . $varData->size . ')';
        }
        $output .= ' ' . $this->colorize($type, 'type', false);

        if ($varData->value !== null && $varData->value !== '') {
            $output .= ' ' . $this->colorize((string)$varData->value, 'value', false);
        }

        return ltrim($output);
    }

    public function init(): string
    {
        if (!Dump::$cliColors) {
            self::$_enableColors = false;
        } elseif (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false) {
            self::$_enableColors = false;
        } elseif (getenv('TERM_PROGRAM') === 'Hyper') {
            self::$_enableColors = true;
        } elseif (DIRECTORY_SEPARATOR === '\\') {
            self::$_enableColors =
                function_exists('sapi_windows_vt100_support')
                || getenv('ANSICON') !== false
                || getenv('ConEmuANSI') === 'ON'
                || getenv('TERM') === 'xterm';
        } else {
            self::$_enableColors = true;
        }

        if (Dump::enabled() !== Dump::MODE_PLAIN) {
            return '';
        }

        return '<style>._dumpper_plain{text-shadow: #eee 0 0 7px;}._dumpper_plain *{display: inline;margin: 0;font-size: 1em}._dumpper_plain h1{color:#5aF}._dumpper_plain var{color:#d11}._dumpper_plain dfn{color:#3d3}._dumpper_plain a{color: inherit;filter: brightness(0.85);}</style>'
            . '<script>window.onload=function(){document.querySelectorAll("._dumpper_plain a").forEach(el=>el.addEventListener("click",e=>{e.preventDefault();let X=new XMLHttpRequest;X.open("GET",e.target.href);X.send()}))}</script>';
    }
}
