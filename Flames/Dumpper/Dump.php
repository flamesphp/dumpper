<?php

namespace Flames\Dumpper;

use Flames\Dumpper\Decorators\DumpDecoratorsPlain;
use Flames\Dumpper\Decorators\DumpDecoratorsRich;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpParser;
use Flames\Dumpper\Inc\DumpTraceStep;

/**
 * Main entry point for the FlamesPHP variable dumper.
 *
 * Usage:
 *   Dump::dump($var);
 *   Dump::trace();
 *
 * @internal
 */
class Dump
{
    private static bool $_initialized = false;
    private static string $_dir = '';

    /**
     * Returns the absolute path to the directory containing this file (with trailing slash).
     */
    public static function dir(): string
    {
        if (self::$_dir === '') {
            self::$_dir = __DIR__ . '/';
        }
        return self::$_dir;
    }

    private static mixed $_enabledMode = true;
    private static mixed $_openedOutput = null;

    /**
     * @var string|null makes visible source file paths clickable to open your editor.
     *
     * Pre-defined values:
     *   'sublime'                => 'subl://open?url=file://%file&line=%line',
     *   'textmate'               => 'txmt://open?url=file://%file&line=%line',
     *   'emacs'                  => 'emacs://open?url=file://%file&line=%line',
     *   'macvim'                 => 'mvim://open/?url=file://%file&line=%line',
     *   'phpstorm'               => 'phpstorm://open?file=%file&line=%line',
     *   'phpstorm-remote'        => 'http://localhost:63342/api/file/%file:%line',
     *   'idea'                   => 'idea://open?file=%file&line=%line',
     *   'vscode'                 => 'vscode://file/%file:%line',
     *   'vscode-insiders'        => 'vscode-insiders://file/%file:%line',
     *   'vscode-remote'          => 'vscode://vscode-remote/%file:%line',
     *   'vscode-insiders-remote' => 'vscode-insiders://vscode-remote/%file:%line',
     *   'vscodium'               => 'vscodium://file/%file:%line',
     *   'atom'                   => 'atom://core/open/file?filename=%file&line=%line',
     *   'nova'                   => 'nova://core/open/file?filename=%file&line=%line',
     *   'netbeans'               => 'netbeans://open/?f=%file:%line',
     *   'xdebug'                 => 'xdebug://%file@%line'
     *
     * Or pass a custom string where %file should be replaced with full file path, %line with line number.
     * Set to null to disable linking.
     */
    public static mixed $editor = null;

    /** @var string|null the full path (not URL) to your project folder on your remote dev server. */
    public static ?string $fileLinkServerPath = null;

    /** @var string|null the full path (not URL) to your project on your local machine. */
    public static ?string $fileLinkLocalPath = null;

    /** @var bool|null whether to display where Dump was called from. */
    public static ?bool $displayCalledFrom = null;

    /** @var int|false max array/object levels to go deep, set to zero/false to disable. */
    public static int|false $maxLevels = 20;

    /** @var bool|null draw rich output already expanded without having to click. */
    public static ?bool $expandedByDefault = null;

    /** @var bool|null enable detection when running in command line and adjust output format accordingly. */
    public static ?bool $cliDetection = null;

    /** @var bool|null enable ANSI colors in *UNIX* CLI mode. */
    public static ?bool $cliColors = null;

    /** @var array|null possible alternative char encodings in order of probability. */
    public static ?array $charEncodings = null;

    /**
     * @var bool|string|null Dump returns output instead of echo.
     *
     * If true, the return has scripts+css always included,
     * if set to a string, only first time per "group".
     */
    public static bool|string|null $returnOutput = null;

    /** @var string|null Write output to this file instead of echoing it. */
    public static ?string $outputFile = null;

    /** @var array Add new custom Dump wrapper names for nice backtraces and variable name detection. */
    public static array $aliases = [];

    /** @var string[] trace path blacklist patterns (regex). */
    public static array $traceBlacklist = [
        'vendor'     => '#\/vendor\/#',
        'middleware' => '#\/Middleware\/#',
    ];

    public static array $classNameBlacklist = [
        'illuminate' => '/^Illuminate(?!.*(?:Exception|Collection))/',
    ];

    public static array $arrayKeysBlacklist = [];

    public static int $minimumTraceStepsToShowFull = 1;

    /** @var array<string, bool> */
    public static array $enabledParsers = [
        'DumpParsersFlamesModel'       => true,
        'DumpParsersSmarty'            => true,
        'DumpParsersSplFileInfo'       => true,
        'DumpParsersClosure'           => true,
        'DumpParsersEloquent'          => true,
        'DumpParsersDateTime'          => true,
        'DumpParsersSplObjectStorage'  => true,
        'DumpParsersTimestamp'         => true,
        'DumpParsersFilePath'          => true,
        'DumpParsersBlacklist'         => true,
        'DumpParsersXml'               => true,
        'DumpParsersObjectIterateable' => true,
        'DumpParsersClassStatics'      => true,
        'DumpParsersColor'             => true,
        'DumpParsersJson'              => true,
        'DumpParsersClassName'         => true,
        'DumpParsersMicrotime'         => true,
    ];

    const MODE_RICH      = 'r';
    const MODE_TEXT_ONLY = 'w';
    const MODE_CLI       = 'c';
    const MODE_PLAIN     = 'p';

    public static bool $simplifyDisplay = false;

    private static int $loadedParsers = 0;

    /**
     * Saves or restores the complete Dump state (used internally for test isolation).
     */
    public static function saveState(?array $state = null): ?array
    {
        $rich  = new DumpDecoratorsRich();
        $plain = new DumpDecoratorsPlain();

        if ($state !== null) {
            self::$_enabledMode       = $state['enabled'];
            self::$editor             = $state['editor'];
            self::$fileLinkServerPath = $state['fileLinkServerPath'];
            self::$fileLinkLocalPath  = $state['fileLinkLocalPath'];
            self::$displayCalledFrom  = $state['displayCalledFrom'];
            self::$maxLevels          = $state['maxLevels'];
            self::$expandedByDefault  = $state['expandedByDefault'];
            self::$cliDetection       = $state['cliDetection'];
            self::$cliColors          = $state['cliColors'];
            self::$charEncodings      = $state['charEncodings'];
            self::$returnOutput       = $state['returnOutput'];
            self::$outputFile         = $state['outputFile'];
            self::$aliases            = $state['aliases'];
            self::$traceBlacklist     = $state['traceBlacklist'];
            self::$classNameBlacklist = $state['classNameBlacklist'];
            self::$enabledParsers     = $state['enabledParsers'];

            $rich->setAssetsNeeded($state['DumpDecoratorsRich::firstRun']);
            $plain->setAssetsNeeded($state['DumpDecoratorsPlain::firstRun']);

            return null;
        }

        return [
            'enabled'                       => self::$_enabledMode,
            'editor'                        => self::$editor,
            'fileLinkServerPath'            => self::$fileLinkServerPath,
            'fileLinkLocalPath'             => self::$fileLinkLocalPath,
            'displayCalledFrom'             => self::$displayCalledFrom,
            'maxLevels'                     => self::$maxLevels,
            'expandedByDefault'             => self::$expandedByDefault,
            'cliDetection'                  => self::$cliDetection,
            'cliColors'                     => self::$cliColors,
            'charEncodings'                 => self::$charEncodings,
            'returnOutput'                  => self::$returnOutput,
            'outputFile'                    => self::$outputFile,
            'aliases'                       => self::$aliases,
            'traceBlacklist'                => self::$traceBlacklist,
            'classNameBlacklist'            => self::$classNameBlacklist,
            'enabledParsers'                => self::$enabledParsers,
            'DumpDecoratorsRich::firstRun'  => $rich->areAssetsNeeded(),
            'DumpDecoratorsPlain::firstRun' => $plain->areAssetsNeeded(),
        ];
    }

    /**
     * Enables or disables Dump, and forces display mode. Also returns currently active mode.
     *
     * @param mixed $forceMode
     *   null      — return current mode
     *   false     — disable Dump
     *   true      — enable and auto-detect best formatting
     *   Dump::MODE_* — enable and force selected mode
     *
     * @return mixed previously set value
     */
    public static function enabled(mixed $forceMode = null): mixed
    {
        if (isset($forceMode)) {
            $before = self::$_enabledMode;
            self::$_enabledMode = $forceMode;
            return $before;
        }

        return self::$_enabledMode;
    }

    /**
     * Prints a debug backtrace, same as `Dump::dump(1)`.
     *
     * @param array|null $trace custom trace; defaults to debug_backtrace()
     */
    public static function trace(?array $trace = null): mixed
    {
        if ($trace === null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
        }

        return self::dump($trace);
    }

    /**
     * Dump information about one or more variables.
     *
     * Supported prefix modifiers (place immediately before the call):
     *   !     — ignore depth limits
     *   print — write output to dumpper.html in the caller's directory
     *   ~     — simplify view (rich→plain, plain→text)
     *   -     — clean output buffers before dumping
     *   +     — expand all nodes
     *   @     — return output instead of echoing
     *
     * @return string|int returns 5463 (Dump in l33tspeak) when disabled
     */
    public static function dump(mixed ...$args): string|int
    {
        try {
            return self::doDump(...$args);
        } catch (\Throwable $e) {
        }

        return 5463;
    }

    /**
     * Internal implementation of dump(); called after exception guard.
     */
    public static function doDump(mixed ...$args): string|int
    {
        $enabledMode = self::enabled();

        if (!$enabledMode) {
            return 5463;
        }

        self::_init();

        [$names, $modifiers, $callee, $previousCaller, $miniTrace] = self::_getCalleeInfo();

        $hasMods  = $modifiers !== null && $modifiers !== '';
        $modPrint = $hasMods && str_contains($modifiers, 'print');
        $modTilde = $hasMods && str_contains($modifiers, '~');
        $modMinus = $hasMods && str_contains($modifiers, '-');
        $modPlus  = $hasMods && str_contains($modifiers, '+');
        $modBang  = $hasMods && str_contains($modifiers, '!');
        $modAt    = $hasMods && str_contains($modifiers, '@');

        if ($enabledMode === true) {
            if ($modPrint && isset($callee['file'])) {
                $newMode = self::MODE_RICH;
            } elseif (self::$outputFile && str_ends_with(self::$outputFile, '.html')) {
                $newMode = self::MODE_RICH;
            } else {
                $newMode = \Flames\Forge\Cli::isCli()
                    ? self::MODE_CLI
                    : self::MODE_RICH;
            }

            if (self::$simplifyDisplay) {
                $newMode = match ($newMode) {
                    self::MODE_RICH => self::MODE_PLAIN,
                    self::MODE_CLI  => self::MODE_TEXT_ONLY,
                    default         => $newMode,
                };
            }

            if ($modTilde) {
                $newMode = match ($newMode) {
                    self::MODE_RICH               => self::MODE_PLAIN,
                    self::MODE_PLAIN, self::MODE_CLI => self::MODE_TEXT_ONLY,
                    default                        => $newMode,
                };
            }

            self::enabled($newMode);
        }

        $decorator = self::enabled() === self::MODE_RICH
            ? new DumpDecoratorsRich()
            : new DumpDecoratorsPlain();

        $firstRunOldValue = $decorator->areAssetsNeeded();

        if ($modMinus) {
            $decorator->setAssetsNeeded(true);
            while (ob_get_level()) {
                ob_end_clean();
            }
        }
        if ($modPlus) {
            $expandedByDefaultOldValue = self::$expandedByDefault;
            self::$expandedByDefault = true;
        }
        if ($modBang) {
            $maxLevelsOldValue = self::$maxLevels;
            self::$maxLevels = false;
        }
        if ($modAt) {
            $returnOldValue = self::$returnOutput;
            self::$returnOutput = true;
        }
        if (self::$returnOutput) {
            if (self::$returnOutput === true) {
                $decorator->setAssetsNeeded(true);
            } elseif (!isset(self::$_openedOutput[self::$returnOutput])) {
                $decorator->setAssetsNeeded(true);
                self::$_openedOutput[self::$returnOutput] = true;
            }
        }

        if ($modPrint && isset($callee['file'])) {
            $outputFileOldValue = self::$outputFile;
            self::$outputFile = dirname($callee['file']) . '/dumpper.html';
        }

        if (self::$outputFile && !isset(self::$_openedOutput[self::$outputFile])) {
            $firstRunOldValue = $decorator->areAssetsNeeded();
            $decorator->setAssetsNeeded(true);
        }

        $trace      = false;
        $lightTrace = false;
        $argCount   = count($args);
        $data       = $args[0] ?? null;

        if ($argCount === 1) {
            if ($names === ['1'] && $data === 1) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
            } elseif ($names === ['2'] && $data === 2) {
                $lightTrace = true;
                $trace = debug_backtrace();
            } elseif (is_array($data)) {
                $trace = $data;
            }
        }

        if ($trace) {
            $trace = self::_parseTrace($trace);
        }

        $output = '';
        if ($decorator->areAssetsNeeded()) {
            $output .= $decorator->init();
        }
        $output .= $decorator->wrapStart();

        if ($trace) {
            $output .= $decorator->decorateTrace($trace, $lightTrace);
        } else {
            if ($argCount === 0) {
                DumpParser::reset();
                $tmp     = microtime();
                $varData = DumpParser::process($tmp, '');
                $varData->type  = null;
                $varData->value = null;
                $varData->size  = null;
                $varData->name  = 'Dump called with no arguments';
                if (!empty($callee['function'])) {
                    $name = isset($callee['class'], $callee['type'])
                        ? $callee['class'] . $callee['type'] . $callee['function']
                        : $callee['function'];
                    $varData->name = $name . '( no parameters )';
                }
                $output .= $decorator->decorate($varData);
            } else {
                foreach ($args as $k => $argument) {
                    DumpParser::reset();
                    $name = empty($names[$k]) ? '???' : $names[$k];
                    if (self::_isDumpLiteralArgName($name)) {
                        $name = null;
                    }
                    $output .= $decorator->decorate(
                        DumpParser::process($argument, $name)
                    );
                }
            }
        }

        $output .= $decorator->wrapEnd($callee, $miniTrace, $previousCaller);

        if (self::$outputFile) {
            if (!isset(self::$_openedOutput[self::$outputFile])) {
                self::$_openedOutput[self::$outputFile] = fopen(self::$outputFile, 'w');
                $decorator->setAssetsNeeded($firstRunOldValue);
            }
            fwrite(self::$_openedOutput[self::$outputFile], $output);
        }

        self::enabled($enabledMode);
        $decorator->setAssetsNeeded(false);

        if ($hasMods) {
            if ($modTilde) {
                $decorator->setAssetsNeeded($firstRunOldValue);
            }
            if ($modPlus) {
                self::$expandedByDefault = $expandedByDefaultOldValue;
            }
            if (isset($maxLevelsOldValue)) {
                self::$maxLevels = $maxLevelsOldValue;
            }
            if ($modPrint && isset($callee['file'])) {
                $tmp = self::$outputFile;
                self::$outputFile = $outputFileOldValue;
                if (!$modAt) {
                    echo 'Dump -> ' . $tmp . PHP_EOL;
                }
                return 5463;
            }
            if ($modAt) {
                self::$returnOutput = $returnOldValue;
                $decorator->setAssetsNeeded($firstRunOldValue);
                return $output;
            }
        }

        if (self::$returnOutput) {
            return $output;
        }

        if (self::$outputFile) {
            return 5463;
        }

        echo $output;

        return 5463;
    }

    /**
     * Literal values passed directly to dump() (e.g. dump('oi'), dump(123)) are
     * masked as '...' during source parsing — omit the redundant name label.
     */
    private static function _isDumpLiteralArgName(string $name): bool
    {
        if ($name === '???') {
            return false;
        }

        if (preg_match('/^[\'"]\.\.\.[\'"]$/', $name)) {
            return true;
        }

        if (preg_match('/^-?(?:0x[\da-f]+|0b[01]+|0[\da-f]*|\d+(?:_\d+)*(?:\.\d+(?:_\d+)*)?(?:[eE][+-]?\d+)?)$/i', $name)) {
            return true;
        }

        return in_array(strtolower($name), ['true', 'false', 'null'], true);
    }

    /**
     * Returns parameter names passed to the calling dump function, plus any modifiers.
     *
     * @return array{array, string, array, array, array}
     */
    private static function _getCalleeInfo(): array
    {
        $trace                  = debug_backtrace();
        $previousCaller         = [];
        $miniTrace              = [];
        $prevStep               = [];
        $insideTemplateDetected = null;

        while ($step = array_pop($trace)) {
            if (DumpHelper::stepIsInternal($step)) {
                $previousCaller = $prevStep;
                break;
            }

            if (
                isset($step['args'][0])
                && is_string($step['args'][0])
                && str_ends_with($step['args'][0], '.blade.php')
            ) {
                $insideTemplateDetected = $step['args'][0];
            }

            if (isset($step['file'], $step['line'])) {
                unset($step['object'], $step['args']);
                array_unshift($miniTrace, $step);
            }

            $prevStep = $step;
        }
        $callee = $step;

        if (!isset($callee['file']) || !is_readable($callee['file'])) {
            return [null, null, $callee, $previousCaller, $miniTrace];
        }

        $file   = fopen($callee['file'], 'r');
        $line   = 0;
        $source = '';
        while (($row = fgets($file)) !== false) {
            if (++$line > $callee['line']) {
                break;
            }
            $source .= $row;
        }
        fclose($file);
        $source = self::_removeAllButCode($source);

        $codePattern = empty($callee['class'])
            ? $callee['function']
            : "\w+\x07*" . $callee['type'] . "\x07*" . $callee['function'];

        preg_match_all(
            "
            /
            [\x07{(]
            ([print\x07-+!@~]*)?
            \x07*
            \\\\?
            \x07*
            ({$codePattern})
            \x07*
            (\\()
            /ix",
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $modifiers  = end($matches[1]);
        $callToDump = end($matches[2]);
        $bracket    = end($matches[3]);

        if (empty($callToDump)) {
            return [[], $modifiers, $callee, $previousCaller, $miniTrace];
        }

        $modifiers    = str_replace("\x07", '', $modifiers[0]);
        $paramsString = preg_replace("[\x07+]", ' ', substr($source, $bracket[1] + 1));

        $c              = strlen($paramsString);
        $inString       = $escaped = $openedBracket = $closingBracket = false;
        $i              = 0;
        $inBrackets     = 0;
        $openedBrackets = [];
        $bracketPairs   = ['(' => ')', '[' => ']', '{' => '}'];

        while ($i < $c) {
            $letter = $paramsString[$i];

            if (!$inString) {
                if ($letter === '\'' || $letter === '"') {
                    $inString = $letter;
                } elseif ($letter === '(' || $letter === '[' || $letter === '{') {
                    $inBrackets++;
                    $openedBrackets[] = $openedBracket = $letter;
                    $closingBracket   = $bracketPairs[$letter];
                } elseif ($inBrackets && $letter === $closingBracket) {
                    $inBrackets--;
                    array_pop($openedBrackets);
                    $openedBracket = end($openedBrackets);
                    if ($openedBracket) {
                        $closingBracket = $bracketPairs[$openedBracket];
                    }
                } elseif (!$inBrackets && $letter === ')') {
                    $paramsString = substr($paramsString, 0, $i);
                    break;
                }
            } elseif ($letter === $inString && !$escaped) {
                $inString = false;
            }

            if ($inBrackets > 0) {
                if ($inBrackets > 1 || $letter !== $openedBracket) {
                    $paramsString[$i] = "\x07";
                }
            }
            if ($inString) {
                if ($letter !== $inString || $escaped) {
                    $paramsString[$i] = "\x07";
                }
            }

            $escaped = !$escaped && ($letter === '\\');
            $i++;
        }

        $names = explode(',', preg_replace("[\x07+]", '...', $paramsString));
        $names = array_map('trim', $names);

        if ($insideTemplateDetected) {
            $callee['file'] = $insideTemplateDetected;
            $callee['line'] = null;
        }

        return [$names, $modifiers, $callee, $previousCaller, $miniTrace];
    }

    /**
     * Strips comments and normalises whitespace from PHP source for argument name extraction.
     */
    private static function _removeAllButCode(string $source): string
    {
        $commentTokens = [
            T_COMMENT     => true,
            T_INLINE_HTML => true,
            T_DOC_COMMENT => true,
        ];
        $whiteSpaceTokens = [
            T_WHITESPACE         => true,
            T_CLOSE_TAG          => true,
            T_OPEN_TAG           => true,
            T_OPEN_TAG_WITH_ECHO => true,
        ];

        $cleanedSource = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (isset($commentTokens[$token[0]])) {
                    continue;
                }
                $token = isset($whiteSpaceTokens[$token[0]]) ? "\x07" : $token[1];
            } elseif ($token === ';') {
                $token = "\x07";
            }

            $cleanedSource .= $token;
        }

        return $cleanedSource;
    }

    /**
     * Validates and converts a raw debug_backtrace() result into DumpTraceStep[].
     *
     * @return DumpTraceStep[]|false
     */
    private static function _parseTrace(array $data): array|false
    {
        $trace       = [];
        $traceFields = ['file', 'line', 'args', 'class'];
        $fileFound   = false;
        $lastStep    = [];

        foreach ($data as $step) {
            if (!is_array($step) || !isset($step['function'])) {
                return false;
            }
            if (!$fileFound && isset($step['file']) && file_exists($step['file'])) {
                $fileFound = true;
            }

            $valid = false;
            foreach ($traceFields as $element) {
                if (isset($step[$element])) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                return false;
            }

            if ($step['function'] === 'spl_autoload_call') {
                continue;
            }

            if (DumpHelper::stepIsInternal($step)) {
                if (isset($step['file'], $step['line'])) {
                    $lastStep = [
                        'file'     => $step['file'],
                        'line'     => $step['line'],
                        'function' => '',
                    ];
                }
                continue;
            }

            $trace[] = $step;
        }

        if (!$fileFound) {
            return false;
        }

        if ($lastStep) {
            array_unshift($trace, $lastStep);
        }

        $output = [];
        foreach ($trace as $i => $step) {
            $output[] = new DumpTraceStep($step, $i);
        }

        return $output;
    }

    /**
     * Called before each invocation; loads parsers and sets defaults on first run.
     */
    private static function _init(): void
    {
        DumpHelper::buildAliases();

        $parsersCount = array_sum(self::$enabledParsers);

        if (self::$loadedParsers !== $parsersCount) {
            self::$loadedParsers = $parsersCount;
            $dir = self::dir() . 'Parsers/';
            foreach (self::$enabledParsers as $className => $enabled) {
                if ($enabled) {
                    $f = $dir . $className . '.php';
                    if (file_exists($f)) {
                        require_once $f;
                    }
                }
            }
        }

        if (self::$_initialized) {
            return;
        }

        self::$_initialized = true;

        self::$editor             ??= ini_get('xdebug.file_link_format') ?: 'phpstorm-remote';
        self::$fileLinkServerPath ??= null;
        self::$fileLinkLocalPath  ??= null;
        self::$displayCalledFrom  ??= true;
        self::$expandedByDefault  ??= false;
        self::$cliDetection       ??= true;
        self::$cliColors          ??= true;
        self::$charEncodings      ??= ['UTF-8', 'Windows-1252', 'euc-jp'];
        self::$returnOutput       ??= false;
        self::$aliases            ??= [];
    }
}
