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

    private static $aliasesRaw;
    private static $projectRootDir;

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
     * Rebuilds the internal aliases list from Dump::$aliases.
     *
     * @return void
     */
    public static function buildAliases()
    {
        self::$aliasesRaw = array(
            'methods'   => array(
                array('flames\dumpper\dump', 'dump'),
                array('flames\dumpper\dump', 'doDump'),
                array('flames\dumpper\dump', 'trace'),
            ),
            'functions' => array(),
        );

        foreach (Dump::$aliases as $alias) {
            $alias = strtolower($alias);
            if (strpos($alias, '::') !== false) {
                self::$aliasesRaw['methods'][] = explode('::', $alias);
            } else {
                self::$aliasesRaw['functions'][] = $alias;
            }
        }
    }

    /**
     * Returns whether the given trace step originates inside Dump or one of its wrappers.
     *
     * @param array $step
     * @return bool
     */
    public static function stepIsInternal($step)
    {
        if (isset($step['class'])) {
            foreach (self::$aliasesRaw['methods'] as $alias) {
                if ($alias[0] === strtolower($step['class']) && $alias[1] === strtolower($step['function'])) {
                    return true;
                }
            }
            return false;
        }

        return in_array(strtolower($step['function']), self::$aliasesRaw['functions'], true);
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

        if (function_exists('mb_substr')) {
            $encoding = $encoding ?: self::detectEncoding($string);
            return mb_substr($string, $start, $end, $encoding);
        }

        return substr($string, $start, $end);
    }

    /**
     * Returns true if the array is a sequential (0-indexed) list.
     *
     * @param array $array
     * @return bool
     */
    public static function isArraySequential(array $array)
    {
        $keys = array_keys($array);
        return array_keys($keys) === $keys;
    }

    /**
     * Detects the character encoding of a string.
     *
     * @param string $value
     * @return string encoding label (e.g. 'UTF-8')
     */
    public static function detectEncoding($value)
    {
        if (function_exists('mb_detect_encoding')) {
            $mbDetected = mb_detect_encoding($value);
            if ($mbDetected === 'ASCII') {
                return 'UTF-8';
            }
        }

        if (!function_exists('iconv')) {
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
        if (function_exists('mb_strlen')) {
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

        if (strpos($ideLink, 'http://') === 0) {
            return "<a href=\"{$ideLink}\">{$linkText}</a>";
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
