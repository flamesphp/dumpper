<?php
declare(strict_types=1);


namespace Flames\Dumpper\Inc;

use Flames\Dumpper\Dump;

/**
 * Static utility methods used across the dumpper package.
 *
 * @internal
 */
class DumpHelper
{
    const MAX_STR_LENGTH = 80;

    /** @var array<string, string> editor protocol templates */
    public static array $editors = [
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
    ];

    private static ?string $projectRootDir = null;

    /**
     * Hash-map of internal method aliases: ['classname_lower' => ['methodname_lower' => true]].
     *
     * @var array<string, array<string, true>>|null
     */
    private static ?array $internalMethods = null;

    /**
     * Hash-map of internal function aliases: ['funcname_lower' => true].
     *
     * @var array<string, true>|null
     */
    private static ?array $internalFunctions = null;

    private static int $aliasesCount = -1;

    public static function isRichMode(): bool
    {
        return Dump::enabled() === Dump::MODE_RICH;
    }

    public static function isHtmlMode(): bool
    {
        $mode = Dump::enabled();
        return $mode === Dump::MODE_RICH || $mode === Dump::MODE_PLAIN;
    }

    /**
     * Resolves virtual source paths (e.g. subset stream wrappers) to real files on disk.
     */
    public static function resolveSourcePath(string $file): string
    {
        if (str_starts_with($file, 'flames://')) {
            return substr($file, 9);
        }

        return $file;
    }

    /**
     * Shortens an absolute file path relative to ROOT_PATH when available,
     * otherwise falls back to the common root shared with the dumpper package.
     */
    public static function shortenPath(string $file): string
    {
        $file = self::resolveSourcePath(str_replace('\\', '/', $file));

        if (defined('ROOT_PATH')) {
            $root = rtrim(str_replace('\\', '/', ROOT_PATH), '/') . '/';
            $candidates = [$file];

            $resolved = realpath($file);
            if ($resolved !== false) {
                $candidates[] = str_replace('\\', '/', $resolved);
            }

            if ($file !== '' && $file[0] !== '/' && !preg_match('#^[A-Za-z]:/#', $file)) {
                $joined = realpath($root . $file);
                if ($joined !== false) {
                    $candidates[] = str_replace('\\', '/', $joined);
                }
            }

            foreach ($candidates as $candidate) {
                if (str_starts_with($candidate, $root)) {
                    return substr($candidate, strlen($root));
                }
            }
        }

        if (self::$projectRootDir === null) {
            self::$projectRootDir = '';

            $dumpperPathParts = explode('/', str_replace('\\', '/', Dump::dir()));
            $filePathParts    = explode('/', $file);
            foreach ($filePathParts as $i => $filePart) {
                if (!isset($dumpperPathParts[$i]) || $dumpperPathParts[$i] !== $filePart) {
                    break;
                }
                self::$projectRootDir .= $filePart . '/';
            }
        }

        if (self::$projectRootDir !== '' && str_starts_with($file, self::$projectRootDir)) {
            return substr($file, strlen(self::$projectRootDir));
        }

        return $file;
    }

    /**
     * Rebuilds the internal aliases lookup tables from Dump::$aliases.
     * Uses a count-based cache so it is essentially free when aliases have not changed.
     */
    public static function buildAliases(): void
    {
        $count = count(Dump::$aliases);
        if ($count === self::$aliasesCount && self::$internalMethods !== null) {
            return;
        }
        self::$aliasesCount = $count;

        self::$internalMethods = [
            'flames\dumpper\dump' => ['dump' => true, 'dodump' => true, 'trace' => true],
        ];
        self::$internalFunctions = [];

        foreach (Dump::$aliases as $alias) {
            $alias = strtolower($alias);
            if (str_contains($alias, '::')) {
                [$class, $method] = explode('::', $alias, 2);
                self::$internalMethods[$class][$method] = true;
            } else {
                self::$internalFunctions[$alias] = true;
            }
        }
    }

    /**
     * Returns whether the given trace step originates inside Dump or one of its wrappers.
     */
    public static function stepIsInternal(array $step): bool
    {
        if (isset($step['class'])) {
            return isset(
                self::$internalMethods[strtolower($step['class'])][strtolower($step['function'])]
            );
        }

        return isset(self::$internalFunctions[strtolower($step['function'])]);
    }

    /**
     * Multibyte-aware substr wrapper (mbstring always available on PHP 8.5).
     */
    public static function substr(string $string, int $start, ?int $end, ?string $encoding = null): string
    {
        if ($string === '') {
            return '';
        }

        $encoding ??= self::detectEncoding($string);
        return mb_substr($string, $start, $end, $encoding);
    }

    /**
     * Returns true if the array is a sequential (0-indexed) list.
     */
    public static function isArraySequential(array $array): bool
    {
        return array_is_list($array);
    }

    /**
     * Detects the character encoding of a string.
     */
    public static function detectEncoding(string $value): string
    {
        $detected = mb_detect_encoding($value, null, true);

        if ($detected === false || $detected === 'ASCII') {
            return 'UTF-8';
        }

        foreach (Dump::$charEncodings ?? [] as $encoding) {
            if (mb_check_encoding($value, $encoding)) {
                return $encoding;
            }
        }

        return $detected;
    }

    /**
     * Multibyte-aware strlen wrapper (mbstring always available on PHP 8.5).
     */
    public static function strlen(string $string, ?string $encoding = null): int
    {
        $encoding ??= self::detectEncoding($string);
        return mb_strlen($string, $encoding);
    }

    /**
     * Display label overrides for footer backtrace entries.
     * Returns null when the file should keep its normal path label.
     */
    public static function traceLinkText(string $file, ?int $line): ?string
    {
        $file = self::resolveSourcePath($file);

        if (self::isBootTraceFile($file)) {
            return '[Flames] boot' . ($line ? ':' . $line : '');
        }

        $path   = self::shortenPath(str_replace('\\', '/', $file));
        $prefix = 'vendor/flamesphp/';

        if (str_starts_with($path, $prefix)) {
            $relative = substr($path, strlen($prefix));

            return '[Flames] ' . $relative . ($line ? ':' . $line : '');
        }

        return null;
    }

    private static function isBootTraceFile(string $file): bool
    {
        return self::shortenPath(str_replace('\\', '/', $file)) === 'public/index.php';
    }

    /**
     * Builds a clickable IDE link for a file:line reference.
     */
    public static function ideLink(string $file, ?int $line, ?string $linkText = null): string
    {
        $mode = Dump::enabled();
        $file = self::resolveSourcePath($file);
        $absoluteFile = str_replace('\\', '/', $file);
        $resolved     = realpath($file);
        if ($resolved !== false) {
            $absoluteFile = str_replace('\\', '/', $resolved);
        }
        $displayFile = self::shortenPath($absoluteFile);

        $fileLine = $displayFile;
        if ($line) {
            $fileLine .= ':' . $line;
        } else {
            $line = 0;
        }

        if (!self::isHtmlMode()) {
            return $linkText ?? $fileLine;
        }

        $linkText = $linkText !== null ? self::esc($linkText) : self::esc($fileLine);

        if (!Dump::$editor) {
            return $linkText;
        }

        $realPath = $displayFile;

        if (class_exists('Flames\Environment', false)) {
            $environment = \Flames\Environment::default();
            $localPath   = $environment->DUMP_LOCAL_PATH ?? null;
            $remotePath  = $environment->DUMP_REMOTE_PATH ?? null;

            if (!empty($localPath) && !empty($remotePath)) {
                foreach (get_included_files() as $_file) {
                    if (str_ends_with($_file, $displayFile) || str_ends_with($_file, $absoluteFile)) {
                        $realPath = $_file;
                        break;
                    }
                }

                $realPath = \Flames\Collection\Strings::sub($realPath, \Flames\Collection\Strings::length($remotePath));
                $realPath = $localPath . $realPath;
                $realPath = str_contains($realPath, '\\')
                    ? str_replace('/', '\\', $realPath)
                    : str_replace('\\', '/', $realPath);
            }
        }

        $editorTemplate = self::$editors[Dump::$editor] ?? Dump::$editor;
        $ideLink = str_replace(
            ['%file', '%line', Dump::$fileLinkServerPath],
            [$realPath, $line, Dump::$fileLinkLocalPath],
            $editorTemplate
        );

        if ($mode === Dump::MODE_RICH) {
            $class = str_starts_with($ideLink, 'http://') ? ' class="_dumpper-ide-link" ' : ' ';
            return "<a{$class}href=\"{$ideLink}\">{$linkText}</a>";
        }

        return "<a href=\"{$ideLink}\">{$linkText}</a>";
    }

    /**
     * Escapes a value for safe HTML output and optionally renders invisible characters visibly.
     */
    public static function esc(mixed $value, bool $decode = true): string
    {
        $htmlMode = self::isHtmlMode();
        $str      = $htmlMode
            ? htmlspecialchars((string)$value, ENT_NOQUOTES, 'UTF-8')
            : (string)$value;

        return $decode ? self::decodeStr($str, $htmlMode) : $str;
    }

    /**
     * Makes all invisible characters visible.
     *
     * Handles control chars: \v(11), \f(12), \033(27) via str_replace;
     * remaining 0-8, 14-26, 28-31 via preg_replace_callback.
     * Chars 9(\t), 10(\n), 13(\r) are passed through unchanged.
     */
    private static function decodeStr(string $value, bool $htmlMode): string
    {
        if ($value === '') {
            return '';
        }

        if ($htmlMode) {
            if (htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8') === '') {
                return '‹binary data›';
            }

            $value = str_replace(
                ["\v",          "\f",          "\033"      ],
                ['<u>\v</u>', '<u>\f</u>', '<u>\e</u>'],
                $value
            );

            return preg_replace_callback(
                '/[\x00-\x08\x0E-\x1A\x1C-\x1F]/',
                static fn($m) => '<u>‹0x' . ord($m[0]) . '›</u>',
                $value
            ) ?? $value;
        }

        $value = str_replace(["\v", "\f", "\033"], ['\v', '\f', '\e'], $value);

        return preg_replace_callback(
            '/[\x00-\x08\x0E-\x1A\x1C-\x1F]/',
            static fn($m) => sprintf('\x%02X', ord($m[0])),
            $value
        ) ?? $value;
    }
}
