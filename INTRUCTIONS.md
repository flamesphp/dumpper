# Flames Dumpper — AI / Vibecode Instructions

This document describes the **Flames Dumpper** package so AI-assisted developers can understand, extend, and debug it without reading every source file first.

Everything in this package revolves around a single entry point: the global **`dump()`** function. In Flames applications, `dump()` is a thin wrapper that delegates to `Flames\Dumpper\Dump::dump()`. All configuration, parsing, and rendering flow from that call.

---

## Quick Reference

```php
dump($variable);           // dump one or more variables
dump($a, $b, $c);          // multiple variables in one panel
dump();                    // shows "called with no arguments"
dump(1);                   // full debug backtrace (same as Dump::trace())
dump(2);                   // lightweight backtrace (paths only)
dump($traceArray);         // custom trace array

// Prefix modifiers (placed immediately before dump in source):
!dump($x);                 // ignore depth limits
print dump($x);            // write to dumpper.html in caller's directory
~dump($x);                 // simplify view (rich → plain → text)
-dump($x);                 // clean output buffers before dumping
+dump($x);                 // expand all nodes
@dump($x);                 // return HTML string instead of echoing
```

When dump is **disabled**, `dump()` returns `5463` (l33tspeak for "Dump") and outputs nothing.

---

## Architecture Overview

```
dump()  →  Dump::dump()  →  Dump::doDump()
                                │
                    ┌───────────┼───────────┐
                    ▼           ▼           ▼
              _getCalleeInfo  _parseTrace  DumpParser::process()
              (source parse)  (backtrace)   (variable tree)
                    │                           │
                    ▼                           ▼
              DumpHelper                  DumpVariableData
                    │                           │
                    └───────────┬───────────────┘
                                ▼
                    DumpDecoratorsRich  |  DumpDecoratorsPlain
                    (browser HTML)          (plain HTML / CLI)
```

**Pipeline per call:**

1. Check if dump is enabled (`Dump::enabled()`).
2. Initialize defaults and load enabled parsers (`Dump::_init()`).
3. Parse the caller's source file to recover variable names and prefix modifiers (`Dump::_getCalleeInfo()`).
4. Pick output mode (rich HTML, plain HTML, CLI, or text-only).
5. For each argument (or trace), build a `DumpVariableData` tree via `DumpParser::process()`.
6. Render through a decorator and echo, return, or write to file.

---

## Core Entry Point

### `Flames\Dumpper\Dump`

Main orchestrator. All public configuration lives here as static properties.

#### Constants (display modes)

| Constant | Value | Description |
|---|---|---|
| `MODE_RICH` | `'r'` | Interactive collapsible HTML (default in browser) |
| `MODE_PLAIN` | `'w'` | `<pre>`-based HTML with semantic tags |
| `MODE_CLI` | `'c'` | ANSI-colored terminal output |
| `MODE_TEXT_ONLY` | `'p'` | Plain text, no HTML |

#### Public static properties

| Property | Default | Purpose |
|---|---|---|
| `$editor` | `'phpstorm-remote'` (or xdebug ini) | IDE link protocol for clickable file paths |
| `$fileLinkServerPath` | `null` | Remote server project root for path mapping |
| `$fileLinkLocalPath` | `null` | Local machine project root for path mapping |
| `$displayCalledFrom` | `true` | Show footer with call site and mini backtrace |
| `$maxLevels` | `20` | Max recursion depth for arrays/objects (`false` = unlimited) |
| `$expandedByDefault` | `false` | Start with all nodes expanded in rich mode |
| `$cliDetection` | `true` | Auto-switch to CLI mode when running in terminal |
| `$cliColors` | `true` | Enable ANSI colors in CLI mode |
| `$charEncodings` | `['UTF-8', 'Windows-1252', 'euc-jp']` | Encoding detection fallback order |
| `$returnOutput` | `false` | Return HTML instead of echoing (`true` or group string) |
| `$outputFile` | `null` | Write output to file instead of echoing |
| `$aliases` | `[]` | Custom wrapper function/class names for backtrace skipping |
| `$traceBlacklist` | vendor/middleware regexes | Collapse noisy trace steps |
| `$classNameBlacklist` | Illuminate regex | Skip dumping nested framework objects |
| `$arrayKeysBlacklist` | `[]` | Redact specific array keys as `*REDACTED*` |
| `$minimumTraceStepsToShowFull` | `1` | Always show first N trace steps in full |
| `$enabledParsers` | see source | Toggle individual type parsers on/off |
| `$simplifyDisplay` | `false` | Global simplify mode (rich→plain, CLI→text) |

#### Public methods

##### `dir(): string`
Returns the absolute path to the `Flames/Dumpper/` directory (with trailing slash). Used internally to locate parsers and assets.

##### `enabled(mixed $forceMode = null): mixed`
Get or set dump state.

- `null` — returns current mode (`true`, `false`, or a `MODE_*` constant).
- `false` — disable dump entirely.
- `true` — enable with auto-detected formatting.
- `Dump::MODE_*` — force a specific output mode.

Returns the **previous** value when setting.

##### `trace(?array $trace = null): mixed`
Prints a debug backtrace. Equivalent to `dump(1)` when `$trace` is null (uses `debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT)`).

##### `dump(mixed ...$args): string|int`
Public entry point with exception guard. In development environment, catches throwables and prints a short error message. Returns rendered HTML when `$returnOutput` or `@` modifier is active; otherwise returns `5463`.

##### `doDump(mixed ...$args): string|int`
Internal implementation. Handles:

- Mode detection and modifier processing (`!`, `print`, `~`, `-`, `+`, `@`).
- Trace vs variable dumping.
- Decorator selection (`DumpDecoratorsRich` vs `DumpDecoratorsPlain`).
- Output routing (echo, return, file write).

##### `saveState(?array $state = null): ?array`
Saves or restores the complete dump configuration. Used for test isolation. When called with no argument, returns a snapshot array; when called with a snapshot, restores all settings including decorator asset flags.

#### Private methods

| Method | Role |
|---|---|
| `_isDumpLiteralArgName(string $name): bool` | Hides redundant labels for literal values (`'hello'`, `123`, `true`). |
| `_getCalleeInfo(): array` | Reads caller source file; extracts variable names and prefix modifiers. Returns `[names, modifiers, callee, previousCaller, miniTrace]`. |
| `_removeAllButCode(string $source): string` | Strips comments/whitespace from source using `token_get_all()` for reliable parsing. |
| `_parseTrace(array $data): DumpTraceStep[]\|false` | Validates and converts raw backtrace into `DumpTraceStep` objects; filters internal dump frames. |
| `_init(): void` | First-run defaults, alias building, lazy parser loading. |

---

## Internal Support Classes

### `Flames\Dumpper\Inc\DumpVariableData`

Data transfer object representing one node in the dump tree.

#### Public properties

| Property | Type | Description |
|---|---|---|
| `$type` | `?string` | PHP type label (e.g. `string`, `array`, class name) |
| `$access` | `?string` | Visibility: `public`, `protected`, `private`, optionally `readonly` or `[uninitialized]` |
| `$name` | `int\|string\|null` | Variable or property name |
| `$operator` | `?string` | Connector shown in output: `=>` or `->` or `::` |
| `$size` | `int\|string\|null` | Element count, or `"enum"` for enums |
| `$extendedValue` | `array\|string\|null` | Nested children or pre-rendered HTML |
| `$value` | `int\|float\|string\|null` | Short inline display value |

#### Methods

##### `addTabToView(mixed $originalVariable, string $tabName, mixed $value): void`
Adds an alternative tab view in rich mode (e.g. "Json", "Model contents", "CSS color"). Arrays are parsed via `DumpParser::alternativesParse()`.

##### `getAllRepresentations(): array`
Returns all views merged, with `"Contents"` first, then alternative tabs. Used by `DumpDecoratorsRich` to build tabbed UI.

---

### `Flames\Dumpper\Inc\DumpParser`

Recursively converts any PHP value into a `DumpVariableData` tree.

#### Public static properties

| Property | Description |
|---|---|
| `$_level` | Current recursion depth (used by blacklist parser) |

#### Public methods

##### `reset(): void`
Clears recursion state (`$_level`, object hash tracker, marker) between separate arguments in one `dump()` call.

##### `process(mixed &$variable, string|int|null $name = null): DumpVariableData`
Main parsing entry. Flow:

1. Increment depth, create `DumpVariableData`, set/truncate name if > 60 chars.
2. Iterate `$enabledParsers` in order — each parser may augment or replace parsing.
3. If a parser returns non-`false` and `replacesAllOtherParsers()` is true, stop and return.
4. Otherwise fall back to built-in type handlers (`_parse_array`, `_parse_object`, etc.).

##### `alternativesParse(mixed $originalVar, array|object $alternativesArray): array|string|null`
Parses an alternative representation (used by tabs and specialized parsers) while preserving recursion markers for the original variable.

#### Private type handlers

| Method | Handles |
|---|---|
| `_parse_array()` | Arrays/lists; detects tabular data (uniform-key rows → HTML table in rich mode); recursion and depth limits; `$GLOBALS` safety; blacklisted keys |
| `_parse_object()` | Objects via cast + reflection; respects `#[Hidden]` properties; links user-defined classes to source; enum display |
| `_parse_boolean()` | `true` / `FALSE` |
| `_parse_double()` | Float values |
| `_parse_integer()` | Integer values |
| `_parse_null()` | NULL type |
| `_parse_resource()` | Resources; shows stream URI when available |
| `_parse_string()` | Strings with encoding detection, truncation, invisible character tabs |
| `_parse_unknown()` | Fallback via `var_export()` |

#### Private helpers

| Method | Role |
|---|---|
| `isDepthLimit(): bool` | Checks `Dump::$maxLevels` against `$_level` |
| `_isArrayTabular(array): array\|false` | Detects array-of-uniform-arrays for table rendering |
| `_decorateCell(DumpVariableData): string` | Builds `<td>` for tabular display |
| `getObjectHash(object): int` | Uses `spl_object_id()` for recursion detection |
| `isPropertyHidden(ReflectionProperty): bool` | Checks for `#[Hidden]` attribute |

---

### `Flames\Dumpper\Inc\DumpHelper`

Static utilities shared across the package.

#### Constants

- `MAX_STR_LENGTH = 80` — string truncation threshold in rich mode.

#### Public static properties

- `$editors` — map of editor name → URL template (`%file`, `%line` placeholders).

#### Methods

| Method | Description |
|---|---|
| `isRichMode(): bool` | `Dump::enabled() === MODE_RICH` |
| `isHtmlMode(): bool` | Rich or plain mode (HTML output) |
| `resolveSourcePath(string $file): string` | Resolves `flames://` virtual paths to real paths |
| `shortenPath(string $file): string` | Shortens absolute paths relative to `ROOT_PATH` or project root |
| `buildAliases(): void` | Builds internal lookup tables from `Dump::$aliases` for backtrace filtering |
| `stepIsInternal(array $step): bool` | Whether a backtrace step is inside dump/wrapper code |
| `substr(string, int, ?int, ?string): string` | Multibyte-safe substring |
| `isArraySequential(array): bool` | Whether array is a list (`array_is_list`) |
| `detectEncoding(string): string` | Character encoding detection |
| `strlen(string, ?string): int` | Multibyte-safe length |
| `traceLinkText(string $file, ?int $line): ?string` | Custom labels for Flames framework paths in footer |
| `ideLink(string $file, ?int $line, ?string $linkText): string` | Clickable IDE link (HTML `<a>` or plain text) |
| `esc(mixed $value, bool $decode = true): string` | HTML escape + optional invisible character visualization |

---

### `Flames\Dumpper\Inc\DumpTraceStep`

Represents one frame in a debug backtrace.

#### Public properties

| Property | Description |
|---|---|
| `$functionName` | Fully qualified call signature with parameter names |
| `$isBlackListed` | Whether this step was collapsed by trace blacklist |
| `$fileLine` | Clickable file:line link |
| `$sourceSnippet` | Surrounding source code (rich mode only, ±7 lines) |
| `$arguments` | Parsed argument values as `DumpVariableData[]` |
| `$argumentNames` | Parameter names from reflection (`$name` or `#1`) |
| `$object` | Callee object parsed as `DumpVariableData` |

#### Constructor

`__construct(array $step, int $stepNumber)` — builds all properties from a raw `debug_backtrace()` entry.

#### Private methods

| Method | Role |
|---|---|
| `isStepBlacklisted()` | Checks `$traceBlacklist` regex patterns |
| `getFileAndLine()` | IDE link for file location |
| `getStepArgumentNames()` | Reflection-based parameter names |
| `getStepFunctionName()` | Formatted `Class->method($a, $b)` string |
| `getObject()` | Parses `$step['object']` if present |
| `getSourceSnippet()` | Reads and highlights source around call line |
| `getArguments()` | Parses each argument value |
| `getRawArguments()` | Handles include/require special case |

---

## Output Decorators

### `Flames\Dumpper\Decorators\DumpDecoratorsInterface`

Contract for all output renderers.

| Method | Description |
|---|---|
| `decorate(DumpVariableData $varData): string` | Render a single variable node |
| `decorateTrace(DumpTraceStep[] $traceData, bool $pathsOnly): string` | Render a full backtrace |
| `wrapStart(): string` | Open outer wrapper for one dump call |
| `wrapEnd(array $callee, array $miniTrace, array $prevCaller): string` | Close wrapper + footer with call site info |
| `init(): string` | CSS/JS assets for first render (may be empty) |

---

### `Flames\Dumpper\Decorators\DumpDecoratorsRich`

Interactive HTML output with collapsible nodes, tabs, and source highlighting.

#### Methods

| Method | Description |
|---|---|
| `areAssetsNeeded(): bool` | Whether CSS/JS should be included this render |
| `setAssetsNeeded(bool): void` | Control asset injection (for grouped/return output) |
| `decorate(DumpVariableData): string` | Renders `<dl>/<dt>/<dd>` tree with tabs for alternative views |
| `decorateTrace(DumpTraceStep[], bool $pathsOnly): string` | Renders trace with Source/Arguments/Callee Object tabs |
| `wrapStart(): string` | `<div class="_dumpper">` with close button |
| `wrapEnd(...): string` | Footer with "Called from", parent function, timestamp, mini trace |
| `init(): string` | Loads `material-kit.css`, `dumpper.js`, optional `dark.css` from resources |

#### Private methods

| Method | Role |
|---|---|
| `drawTraceStep()` | Single trace frame with tabs |
| `_drawHeader()` | Type, name, access, size header line |
| `decorateAlternativeView()` | Renders tab content (nested tree or `<pre>`) |

---

### `Flames\Dumpper\Decorators\DumpDecoratorsPlain`

Plain-text and CLI output. Also used for simplified HTML (`MODE_PLAIN`).

#### Methods

| Method | Description |
|---|---|
| `areAssetsNeeded(): bool` | Asset flag (plain mode injects minimal CSS/JS) |
| `setAssetsNeeded(bool): void` | Control asset injection |
| `decorate(DumpVariableData, int $level = 0): string` | Indented tree with colorized headers |
| `decorateTrace(DumpTraceStep[], bool $pathsOnly): string` | Box-drawn trace with argument sections |
| `wrapStart(): string` | `<pre class="_dumpper_plain">` in plain mode, empty in CLI |
| `wrapEnd(...): string` | Call stack footer with file links |
| `init(): string` | Detects terminal color support; injects plain-mode CSS/JS |

#### Private methods

| Method | Role |
|---|---|
| `colorize(mixed $text, string $type, bool $nlAfter): string` | ANSI or HTML semantic coloring by type (`key`, `access`, `value`, `type`, `header`) |
| `title(string $text): string` | Boxed title header |
| `drawHeader(DumpVariableData): string` | Single-line type/value summary |

---

## Attribute

### `Flames\Dumpper\Attribute\Hidden`

```php
#[Hidden]
private string $password;
```

PHP attribute targeting properties and methods. Properties marked `#[Hidden]` are excluded from object dumps (checked in `DumpParser::_parse_object()` and `DumpParsersClassStatics`).

No methods — marker attribute only.

---

## Type Parsers

All parsers implement `DumpParserInterface` and are loaded lazily based on `Dump::$enabledParsers`.

### `Flames\Dumpper\Parsers\DumpParserInterface`

| Method | Description |
|---|---|
| `replacesAllOtherParsers(): bool` | If true, a successful parse skips default type handling |
| `parse(mixed &$variable, mixed $varData): mixed` | Returns `false` if not applicable; otherwise populates `$varData` |

**Parser execution order** (as defined in `Dump::$enabledParsers`):

1. `DumpParsersFlamesModel`
2. `DumpParsersFlamesCollection`
3. `DumpParsersSmarty`
4. `DumpParsersSplFileInfo`
5. `DumpParsersClosure`
6. `DumpParsersEloquent`
7. `DumpParsersDateTime`
8. `DumpParsersSplObjectStorage`
9. `DumpParsersTimestamp`
10. `DumpParsersFilePath`
11. `DumpParsersBlacklist`
12. `DumpParsersXml`
13. `DumpParsersObjectIterateable`
14. `DumpParsersClassStatics`
15. `DumpParsersColor`
16. `DumpParsersJson`
17. `DumpParsersClassName`
18. `DumpParsersMicrotime`

---

### Individual parsers

#### `DumpParsersFlamesModel`
- **Target:** `Flames\Orm\Model` instances (rich mode only).
- **Replaces all:** No — adds tabs alongside default object parsing.
- **Tabs:** "Model contents (N)", "Model details" (database, table).

#### `DumpParsersFlamesCollection`
- **Target:** `Flames\Collection\Arr` / `ArrImmutable`.
- **Replaces all:** Yes — renders as native PHP array.
- **Behavior:** Calls `toArray()`, sets type to `array`, fills `extendedValue`.

#### `DumpParsersSmarty`
- **Target:** `Smarty` template engine instances.
- **Replaces all:** Yes.
- **Tabs:** "Assigned to view", "Assigned globally", "Configuration".

#### `DumpParsersSplFileInfo`
- **Target:** `SplFileInfo` (not `SplFileObject`).
- **Replaces all:** Yes.
- **Behavior:** Shows path, file type, permissions, size, timestamps, symlink info. Rich mode uses tabs; plain mode uses `extendedValue`.
- **`run()`:** Shared logic also used by `DumpParsersFilePath`.
- **`humanFilesize()`:** Formats bytes with unit suffix.

#### `DumpParsersClosure`
- **Target:** PHP `Closure` objects.
- **Replaces all:** Yes.
- **Tabs:** "Closure Parameters", "Closure uses" (static variables + bound `$this`).
- **Value:** IDE link to definition file:line.

#### `DumpParsersEloquent`
- **Target:** `Illuminate\Database\Eloquent\Model`.
- **Replaces all:** Yes.
- **Behavior:** Extracts `$attributes` via reflection; shows database.table reference.

#### `DumpParsersDateTime`
- **Target:** `DateTimeInterface` implementations.
- **Replaces all:** Yes.
- **Value:** Formatted timestamp with microseconds and timezone.

#### `DumpParsersSplObjectStorage`
- **Target:** `SplObjectStorage` (rich mode, non-empty).
- **Replaces all:** No.
- **Tab:** "Storage contents (N)".

#### `DumpParsersTimestamp`
- **Target:** Unix timestamp integers/strings (9–10 digits, or 13-digit JS ms timestamps).
- **Replaces all:** No.
- **Tab:** "Timestamp" with formatted date.

#### `DumpParsersFilePath`
- **Target:** Strings that look like readable file paths (extends `DumpParsersSplFileInfo`).
- **Replaces all:** No.
- **Behavior:** Validates path (length, separators, readability), wraps in `SplFileInfo`, delegates to `run()`.

#### `DumpParsersBlacklist`
- **Target:** Objects whose class matches `Dump::$classNameBlacklist` regexes (depth > 1 only).
- **Replaces all:** Yes.
- **Behavior:** Sets type to `ClassName [skipped]`, prevents deep framework object expansion.

#### `DumpParsersXml`
- **Target:** None (placeholder).
- **Behavior:** Always returns `false`. Reserved for future XML parsing.

#### `DumpParsersObjectIterateable`
- **Target:** `Traversable` DOM objects (`DOMN*` classes, rich mode).
- **Replaces all:** No.
- **Tab:** "Iterator contents (N)".

#### `DumpParsersClassStatics`
- **Target:** Any object with static properties (rich mode).
- **Replaces all:** No.
- **Tab:** "Static class properties (N)" — respects `#[Hidden]`.

#### `DumpParsersColor`
- **Target:** CSS color strings (named, hex, rgb/rgba, hsl/hsla).
- **Replaces all:** No.
- **Tab:** "CSS color" with preview swatch and hex/rgb/hsl conversions.
- **Private:** `_fits()`, `_convert()`, `_HSLtoRGB()`, `_hue2rgb()`, `_RGBtoHSL()`.

#### `DumpParsersJson`
- **Target:** Valid JSON strings starting with `{` or `[`.
- **Replaces all:** No.
- **Tab:** "Json" with decoded array.

#### `DumpParsersClassName`
- **Target:** Strings that are valid user-defined class names.
- **Replaces all:** No.
- **Tab/Value:** IDE link to class definition file.

#### `DumpParsersMicrotime`
- **Target:** Strings matching `microtime()` format (`0.12345678 1234567890`).
- **Replaces all:** No.
- **Behavior:** Tracks consecutive calls; shows lap times, averages, memory usage.
- **First call:** Shows parsed datetime + memory.
- **Subsequent calls:** "Benchmark" tab (rich) or extended values (plain).

---

## Common `dump()` Usage Patterns

### Configuration before dumping

```php
Dump::$editor = 'vscode';
Dump::$maxLevels = 10;
Dump::$expandedByDefault = true;
Dump::$classNameBlacklist['myvendor'] = '/^MyVendor\\\\Internal/';
Dump::$enabledParsers['DumpParsersJson'] = false;
Dump::$aliases[] = 'my_debug';  // treat my_debug() as internal in traces
```

### Capture output as string

```php
Dump::$returnOutput = true;
$html = dump($data);  // or @dump($data)

// Group mode: assets included only on first call per group key
Dump::$returnOutput = 'my-group';
```

### Write to file

```php
Dump::$outputFile = '/tmp/debug.html';
dump($data);

// Or use the print modifier:
print dump($data);  // writes to dumpper.html next to caller file
```

### Disable temporarily

```php
$prev = Dump::enabled(false);
// ... code that should not dump ...
Dump::enabled($prev);
```

---

## Extending Dumpper

### Add a custom parser

1. Create `Flames/Dumpper/Parsers/DumpParsersMyThing.php` implementing `DumpParserInterface`.
2. Register in `Dump::$enabledParsers`:

```php
Dump::$enabledParsers['DumpParsersMyThing'] = true;
```

3. Implement `parse()` — return `false` if not your type; populate `$varData` otherwise.
4. Use `$varData->addTabToView()` for extra rich-mode tabs.
5. Return `true` from `replacesAllOtherParsers()` only if default object/array parsing should be skipped entirely.

### Hide sensitive properties

```php
use Flames\Dumpper\Attribute\Hidden;

class User {
    #[Hidden]
    private string $apiToken;
}
```

### Register wrapper aliases

If your project defines `function dd($x) { dump($x); }`, add it so backtraces skip the wrapper:

```php
Dump::$aliases[] = 'dd';
// or for static methods:
Dump::$aliases[] = 'MyApp\\Debug::dd';
```

---

## Key Behaviors to Remember

1. **Variable names** are extracted from source code, not runtime. They appear next to dumped values when you pass variables (not literals).
2. **Recursion** is detected via unique markers in arrays and `spl_object_id()` for objects.
3. **Depth limit** defaults to 20 levels; use `!dump()` or `Dump::$maxLevels = false` to disable.
4. **Rich mode** renders tabular arrays (uniform row structure) as HTML tables automatically.
5. **Internal frames** (`Dump::dump`, global `dump()`, registered aliases) are stripped from backtraces.
6. **Return value `5463`** means output was echoed (or dump is disabled) — not an error.
7. **Parser order matters** — earlier parsers run first; blacklist runs at depth > 1 to prevent framework noise.

---

## File Map

```
Flames/Dumpper/
├── Dump.php                          # Main entry, config, orchestration
├── Attribute/
│   └── Hidden.php                    # #[Hidden] attribute
├── Decorators/
│   ├── DumpDecoratorsInterface.php   # Decorator contract
│   ├── DumpDecoratorsRich.php        # Interactive HTML
│   └── DumpDecoratorsPlain.php       # Plain / CLI output
├── Inc/
│   ├── DumpHelper.php                # Utilities (paths, encoding, IDE links)
│   ├── DumpParser.php                # Recursive variable parser
│   ├── DumpTraceStep.php             # Backtrace frame model
│   └── DumpVariableData.php          # Parsed variable DTO
└── Parsers/
    ├── DumpParserInterface.php       # Parser contract
    └── DumpParsers*.php              # 18 type-specific parsers
```

Resources (CSS/JS) live outside this namespace at `resources/` relative to the package root (`material-kit.css`, `dumpper.js`, `dark.css`).
