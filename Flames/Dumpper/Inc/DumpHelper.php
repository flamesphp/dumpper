<?php

namespace Flames\Dumpper\Inc;

use Flames;
use Flames\Dumpper\Dump;

/**
 * Static utility methods used across the dumpper package.
 *
 * @internal
 */
class DumpHelper
{
    private static $_php53;

    const MAX_STR_LENGTH = 80;

    /** @var array<string, string> editor protocol templates */
    public static $editors = array(
        'sublime'                => 'subl://open?url=file://%file&line=%line',
        'textmate'               => 'txmt://open?url=file://%file&line=%line',
        'emacs'                  => 'emacs://open?url=file://%file&line=%line',
        'macvim'                 => 'mvim://open/?url=file://%file&line=%line',
        'phpstorm'               => 'phpstorm://open?file=%file&line=%line',
        'phpstorm-remote'        => 'http://localhost:63342/api/file/%file:%line',
        'idea'                   => 'idea://open?file=%file&line=%line',
        'vscode'                 => 'vscode://file/%file:%line',
        'vscode-insiders'        => 'vscode-insiders://file/%file:%line',
        'vscode-remote'          => 'vscode://vscode-remote/%file:%line',
        'vscode-insiders-remote' => 'vscode-insiders://vscode-remote/%file:%line',
        'vscodium'               => 'vscodium://file/%file:%line',
        'atom'                   => 'atom://core/open/file?filename=%file&line=%line',
        'nova'                   => 'nova://core/open/file?filename=%file&line=%line',
        'netbeans'               => 'netbeans://open/?f=%file:%line',
        'xdebug'                 => 'xdebug://%file@%line',
    );

    private static $projectRootDir;

    /**
     * Hash-map of internal method aliases: ['classname_lower' => ['methodname_lower' => true]].
     * Populated by buildAliases().
     *
     * @var array<string, array<string, true>>|null
     */
    private static $internalMethods = null;

    /**
     * Hash-map of internal function aliases: ['funcname_lower' => true].
     * Populated by buildAliases().
     *
     * @var array<string, true>|null
     */
    private static $internalFunctions = null;

    /** Tracks the Dump::$aliases count used when internalMethods/Functions were last built. */
    private static $aliasesCount = -1;

    /** Cached result of function_exists('mb_substr'). */
    private static $hasMbSubstr = null;

    /** Cached result of function_exists('mb_strlen'). */
    private static $hasMbStrlen = null;

    /** Cached result of function_exists('mb_detect_encoding'). */
    private static $hasMbDetect = null;

    /** Cached result of function_exists('iconv'). */
    private static $hasIconv = null;

    /** Cached result of function_exists('array_is_list'). */
    private static $hasArrayIsList = null;

    /**
     * @return bool true on PHP >= 5.3
     */
    public static function php53orLater()
    {
        if (!isset(self::$_php53)) {
            self::$_php53 = version_compare(PHP_VERSION, '5.3.0') > 0;
        }

        return self::$_php53;
    }

    /**
     * @return bool
     */
    public static function isRichMode()
    {
        return Dump::enabled() === Dump::MODE_RICH;
    }

    /**
     * @return bool true in MODE_RICH or MODE_PLAIN
     */
    public static function isHtmlMode()
    {
        $enabledMode = Dump::enabled();

        return $enabledMode === Dump::MODE_RICH || $enabledMode === Dump::MODE_PLAIN;
    }

    /**
     * Shortens an absolute file path by removing the common root shared with the package directory.
     *
     * @param string $file absolute path
     * @return string
     */
    public static function shortenPath($file)
    {
        $file = str_replace('\\', '/', $file);

        if (!isset(self::$projectRootDir)) {
            self::$projectRootDir = '';

            $dumpperPathParts = explode('/', str_replace('\\', '/', Dump::dir()));
            $filePathParts = explode('/', $file);
            foreach ($filePathParts as $i => $filePart) {
                if (!isset($dumpperPathParts[$i]) || $dumpperPathParts[$i] !== $filePart) {
                    break;
                }
                self::$projectRootDir .= $filePart . '/';
            }
        }

        if (self::$projectRootDir && strpos($file, self::$projectRootDir) === 0) {
            return substr($file, strlen(self::$projectRootDir));
        }

        return $file;
    }

    /**
     * Rebuilds the internal aliases lookup tables from Dump::$aliases.
     *
     * Uses a count-based cache so it is essentially free on repeated calls when aliases
     * have not changed (the common case).
     *
     * @return void
     */
    public static function buildAliases()
    {
        $count = count(Dump::$aliases);
        if ($count === self::$aliasesCount && self::$internalMethods !== null) {
            return;
        }
        self::$aliasesCount = $count;

        self::$internalMethods = array(
            'flames\dumpper\dump' => array('dump' => true, 'dodump' => true, 'trace' => true),
        );
        self::$internalFunctions = array();

        foreach (Dump::$aliases as $alias) {
            $alias = strtolower($alias);
            if (strpos($alias, '::') !== false) {
                $parts = explode('::', $alias, 2);
                self::$internalMethods[$parts[0]][$parts[1]] = true;
            } else {
                self::$internalFunctions[$alias] = true;
            }
        }
    }

    /**
     * Returns whether the given trace step originates inside Dump or one of its wrappers.
     *
     * O(1) hash-map lookup instead of a linear scan.
     *
     * @param array $step
     * @return bool
     */
    public static function stepIsInternal($step)
    {
        if (isset($step['class'])) {
            $class = strtolower($step['class']);
            $func  = strtolower($step['function']);
            return isset(self::$internalMethods[$class][$func]);
        }

        return isset(self::$internalFunctions[strtolower($step['function'])]);
    }

    /**
     * Multibyte-aware substr wrapper.
     *
     * @param string      $string
     * @param int         $start
     * @param int|null    $end
     * @param string|null $encoding
     * @return string
     */
    public static function substr($string, $start, $end, $encoding = null)
    {
        if (!isset($string)) {
            return '';
        }

        if (self::$hasMbSubstr === null) {
            self::$hasMbSubstr = function_exists('mb_substr');
        }
        if (self::$hasMbSubstr) {
            $encoding = $encoding ?: self::detectEncoding($string);
            return mb_substr($string, $start, $end, $encoding);
        }

        return substr($string, $start, $end);
    }

    /**
     * Returns true if the array is a sequential (0-indexed) list.
     *
     * Uses array_is_list() (PHP 8.1+) when available for maximum performance,
     * otherwise falls back to a single-pass O(n) loop.
     *
     * @param array $array
     * @return bool
     */
    public static function isArraySequential(array $array)
    {
        if (self::$hasArrayIsList === null) {
            self::$hasArrayIsList = function_exists('array_is_list');
        }
        if (self::$hasArrayIsList) {
            return array_is_list($array);
        }

        $i = 0;
        foreach ($array as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }

    /**
     * Detects the character encoding of a string.
     *
     * @param string $value
     * @return string encoding label (e.g. 'UTF-8')
     */
    public static function detectEncoding($value)
    {
        if (self::$hasMbDetect === null) {
            self::$hasMbDetect = function_exists('mb_detect_encoding');
        }

        $mbDetected = null;
        if (self::$hasMbDetect) {
            $mbDetected = mb_detect_encoding($value);
            if ($mbDetected === 'ASCII') {
                return 'UTF-8';
            }
        }

        if (self::$hasIconv === null) {
            self::$hasIconv = function_exists('iconv');
        }
        if (!self::$hasIconv) {
            return !empty($mbDetected) ? $mbDetected : 'UTF-8';
        }

        $md5 = md5($value);
        foreach (Dump::$charEncodings as $encoding) {
            if (md5(@iconv($encoding, $encoding, $value)) === $md5) {
                return $encoding;
            }
        }

        return 'UTF-8';
    }

    /**
     * Multibyte-aware strlen wrapper.
     *
     * @param string      $string
     * @param string|null $encoding
     * @return int
     */
    public static function strlen($string, $encoding = null)
    {
        if (self::$hasMbStrlen === null) {
            self::$hasMbStrlen = function_exists('mb_strlen');
        }
        if (self::$hasMbStrlen) {
            $encoding = $encoding ?: self::detectEncoding($string);
            return mb_strlen($string, $encoding);
        }

        return strlen($string);
    }

    /**
     * Builds a clickable IDE link for a file:line reference.
     *
     * Integrates with Flames\Environment for local/remote path mapping when available.
     *
     * @param string      $file     absolute path to the source file
     * @param int|null    $line
     * @param string|null $linkText custom anchor text; defaults to "file:line"
     * @return string HTML anchor tag or plain text
     */
    public static function ideLink($file, $line, $linkText = null)
    {
        $enabledMode = Dump::enabled();
        $file        = self::shortenPath($file);

        $fileLine = $file;
        if ($line) {
            $fileLine .= ':' . $line;
        } else {
            $line = 0;
        }

        if (!self::isHtmlMode()) {
            return $fileLine;
        }

        $linkText = $linkText ? $linkText : $fileLine;
        $linkText = self::esc($linkText);

        if (!Dump::$editor) {
            return $linkText;
        }

        $realPath = $file;

        /* Resolve remote→local path mapping via Flames\Environment when available */
        if (class_exists('Flames\Environment', false)) {
            $environment = Flames\Environment::default();
            $localPath   = $environment->DUMP_LOCAL_PATH ?? null;
            $remotePath  = $environment->DUMP_REMOTE_PATH ?? null;

            if (!empty($localPath) && !empty($remotePath)) {
                $files = get_included_files();
                foreach ($files as $_file) {
                    if (\Flames\Collection\Strings::endsWith($_file, $file)) {
                        $realPath = $_file;
                        break;
                    }
                }

                $realPath = \Flames\Collection\Strings::sub($realPath, \Flames\Collection\Strings::length($remotePath));
                $realPath = ($localPath . $realPath);

                if (\Flames\Collection\Strings::contains($realPath, '\\') === true) {
                    $realPath = str_replace('/', '\\', $realPath);
                } else {
                    $realPath = str_replace('\\', '/', $realPath);
                }
            }
        }

        $ideLink = str_replace(
            array('%file', '%line', Dump::$fileLinkServerPath),
            array($realPath, $line, Dump::$fileLinkLocalPath),
            isset(self::$editors[Dump::$editor]) ? self::$editors[Dump::$editor] : Dump::$editor
        );

        if ($enabledMode === Dump::MODE_RICH) {
            $class = (strpos($ideLink, 'http://') === 0) ? ' class="_dumpper-ide-link" ' : ' ';
            return "<a{$class}href=\"{$ideLink}\">{$linkText}</a>";
        }

        return "<a href=\"{$ideLink}\">{$linkText}</a>";
    }

    /**
     * Escapes a value for safe HTML output and optionally renders invisible characters visibly.
     *
     * @param mixed $value
     * @param bool  $decode whether to make invisible characters visible
     * @return string
     */
    public static function esc($value, $decode = true)
    {
        $value = self::isHtmlMode()
            ? htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8')
            : $value;

        if ($decode) {
            $value = self::decodeStr($value);
        }

        return $value;
    }

    /**
     * Makes all invisible characters visible. HTML-escapes if in HTML mode.
     *
     * @param mixed $value
     * @return string
     */
    private static function decodeStr($value)
    {
        if (is_int($value)) {
            return (string)$value;
        }
        if ($value === '') {
            return '';
        }

        if (self::isHtmlMode()) {
            if (htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8') === '') {
                return '‹binary data›';
            }

            $controlCharsMap = array(
                "\v"   => '<u>\v</u>',
                "\f"   => '<u>\f</u>',
                "\033" => '<u>\e</u>',
                "\t"   => "\t<u>\\t</u>",
                "\r\n" => "<u>\\r\\n</u>\n",
                "\n"   => "<u>\\n</u>\n",
                "\r"   => "<u>\\r</u>",
            );
            $replaceTemplate = '<u>‹0x%d›</u>';
        } else {
            $controlCharsMap = array(
                "\v"   => '\v',
                "\f"   => '\f',
                "\033" => '\e',
            );
            $replaceTemplate = '\x%02X';
        }

        $out = '';
        $i   = 0;
        do {
            $character = $value[$i];
            $ord       = ord($character);
            if ($ord < 32 && $ord !== 9 && $ord !== 10 && $ord !== 13) {
                if (isset($controlCharsMap[$character])) {
                    $out .= $controlCharsMap[$character];
                } else {
                    $out .= sprintf($replaceTemplate, $ord);
                }
            } else {
                $out .= $character;
            }
        } while (isset($value[++$i]));

        return $out;
    }
}
