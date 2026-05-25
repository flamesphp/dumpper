<p align="center">
  <a href="https://flamesphp.com" target="_blank">
    <img src="https://i.postimg.cc/PJKG2cXC/flames.png" width="400" alt="Flames Logo">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/flamesphp/dumpper">
    <img src="https://img.shields.io/packagist/v/flamesphp/dumpper" alt="Latest Stable Version">
    <img src="https://img.shields.io/packagist/l/flamesphp/dumpper" alt="License">
  </a>
</p>

<h3 align="center">flamesphp/dumpper — Beautiful Variable Dumper</h3>

<p align="center">
  A standalone variable debugger for the FlamesPHP ecosystem, based on
  <a href="https://github.com/php-sage/sage">php-sage/sage</a> (a Kint fork).
</p>

---

## Requirements

- PHP **8.5** or higher
- `mbstring` extension (recommended)

---

## Installation

### Via Composer (standalone)

```bash
composer require flamesphp/dumpper
```

### Within FlamesPHP

The framework's autoloader already routes `Flames\Dump\*` to this package automatically — no extra
configuration needed.

---

## Quick Start

```php
<?php

use Flames\Dumpper\Dump;

/* Rich HTML output (default in web context) */
Dump::dump($myVariable);

/* Print backtrace */
Dump::trace();

/* Enable / disable */
Dump::enabled(false);   // suppress all output
Dump::enabled(true);    // re-enable (auto-detects mode)

/* Force a specific mode */
Dump::enabled(Dump::MODE_CLI);        // ANSI-coloured terminal output
Dump::enabled(Dump::MODE_PLAIN);      // Plain HTML <pre>
Dump::enabled(Dump::MODE_TEXT_ONLY);  // Raw plain text
```

The `dump()` and `dd()` global helpers are provided by the framework's `Flames/Dump/Register.php`
glue layer (which stays inside the framework because it integrates with HTTP/WebSocket clients).

---

## Configuration

All settings are public static properties on `Flames\Dump\Dump`:

| Property | Type | Default | Description |
|---|---|---|---|
| `$editor` | `string\|null` | `'phpstorm-remote'` | IDE link protocol for file paths |
| `$maxLevels` | `int` | `7` | Max recursion depth (`false` = unlimited) |
| `$expandedByDefault` | `bool` | `false` | Expand all nodes without clicking |
| `$cliDetection` | `bool` | `true` | Auto-detect CLI and switch to CLI mode |
| `$cliColors` | `bool` | `true` | ANSI colours in CLI mode |
| `$displayCalledFrom` | `bool` | `true` | Show file/line where `dump()` was called |
| `$returnOutput` | `bool\|string` | `false` | Return HTML instead of echoing |
| `$outputFile` | `string\|false` | `false` | Write output to a file |

### IDE Editor Links

```php
Dump::$editor = 'phpstorm';           // phpstorm://open?file=…
Dump::$editor = 'phpstorm-remote';   // http://localhost:63342/api/file/… (default)
Dump::$editor = 'vscode';            // vscode://file/…
Dump::$editor = 'vscode-remote';     // for remote containers
Dump::$editor = 'sublime';
Dump::$editor = 'idea';
```

---

## Dump Modifiers

Prefix the call with modifier characters immediately before the function name:

| Modifier | Effect |
|---|---|
| `!` | Ignore `$maxLevels` depth limit |
| `+` | Expand all nodes |
| `~` | Simplify view (rich→plain→text) |
| `-` | Clear output buffers before dumping |
| `@` | Return output string instead of echoing |
| `print` | Save output to `sage.html` in the caller's directory |

```php
+Dump::dump($deepObject);   // expanded
!Dump::dump($bigArray);     // no depth limit
@Dump::dump($x);            // returns HTML string
```

---

## Parsers

Parsers inspect specific types/values and add extra information tabs in rich mode.
All parsers are enabled by default and configurable via `Dump::$enabledParsers`:

| Parser | What it detects |
|---|---|
| `DumpParsersFlamesModel` | `Flames\Model` instances — shows model data + table info |
| `DumpParsersSmarty` | Smarty template engine objects |
| `DumpParsersSplFileInfo` | `SplFileInfo` — shows permissions, size, timestamps |
| `DumpParsersClosure` | Closures — shows parameters and captured variables |
| `DumpParsersEloquent` | Eloquent `Model` instances |
| `DumpParsersDateTime` | `DateTimeInterface` — formatted date string |
| `DumpParsersSplObjectStorage` | `SplObjectStorage` — iterates contents |
| `DumpParsersTimestamp` | Unix timestamp integers/strings |
| `DumpParsersFilePath` | Strings that look like readable file paths |
| `DumpParsersBlacklist` | Skips objects matching `$classNameBlacklist` |
| `DumpParsersJson` | JSON strings — decoded array tab |
| `DumpParsersObjectIterateable` | `Traversable` objects — iterator contents tab |
| `DumpParsersClassStatics` | Objects with static properties |
| `DumpParsersColor` | CSS color values — swatch + hex/rgb/hsl conversions |
| `DumpParsersMicrotime` | `microtime()` strings — lap/duration benchmarks |
| `DumpParsersClassName` | Strings that are valid class names — links to source |

Disable individual parsers:

```php
Dump::$enabledParsers['DumpParsersTimestamp'] = false;
```

---

## Blacklists

```php
/* Skip objects whose class name matches these patterns */
Dump::$classNameBlacklist['illuminate'] = '/^Illuminate(?!.*Exception)/';

/* Redact specific array keys */
Dump::$arrayKeysBlacklist = ['password', 'token'];

/* Skip trace frames matching these path patterns */
Dump::$traceBlacklist['mylib'] = '#/mylib/#';
```

---

## File Structure

```
vendor/flamesphp/dumpper/
├── composer.json
├── README.md
└── Flames/
    └── Dump/
        ├── Dump.php                      ← main class + DUMP_DIR constant
        ├── Inc/
        │   ├── DumpHelper.php
        │   ├── DumpParser.php
        │   ├── DumpTraceStep.php
        │   └── DumpVariableData.php
        ├── Decorators/
        │   ├── DumpDecoratorsInterface.php
        │   ├── DumpDecoratorsRich.php    ← HTML output with CSS/JS
        │   └── DumpDecoratorsPlain.php   ← plain text / CLI output
        ├── Parsers/
        │   ├── DumpParserInterface.php
        │   ├── DumpParsersBlacklist.php
        │   ├── DumpParsersClassName.php
        │   ├── DumpParsersClassStatics.php
        │   ├── DumpParsersClosure.php
        │   ├── DumpParsersColor.php
        │   ├── DumpParsersDateTime.php
        │   ├── DumpParsersEloquent.php
        │   ├── DumpParsersFilePath.php
        │   ├── DumpParsersFlamesModel.php
        │   ├── DumpParsersJson.php
        │   ├── DumpParsersMicrotime.php
        │   ├── DumpParsersObjectIterateable.php
        │   ├── DumpParsersSmarty.php
        │   ├── DumpParsersSplFileInfo.php
        │   ├── DumpParsersSplObjectStorage.php
        │   ├── DumpParsersTimestamp.php
        │   └── DumpParsersXml.php
        └── resources/
            └── compiled/
                ├── sage.js
                ├── original.css          ← default theme
                ├── original-light.css
                ├── aante-light.css
                ├── solarized.css
                ├── solarized-dark.css    ← used by FlamesPHP by default
                └── dark.css              ← dark overlay (always loaded)
```

---

## Framework Integration

The FlamesPHP framework wires the dumpper automatically:

- `Flames\Kernel` defines `DUMPPER_PATH` pointing to `vendor/flamesphp/dumpper/Flames/`
- `Flames\AutoLoad` routes `Flames\Dumpper\*` classes to `DUMPPER_PATH`
- `Flames/Dump/Register.php` (stays in framework) defines the global `dump()` / `dd()` helpers
  with HTTP/WebSocket client integration

Files that **remain in the framework** (not part of this package):

| File | Reason |
|---|---|
| `Flames/Dump/Register.php` | Uses `Flames\Connection\HttpClient` and `Flames\Connection\Async` |
| `Flames/Dump/Plain.php` | Minimal `dump()` / `dd()` fallback when dumpper is disabled |
| `Flames/Dump/Client.php` | Browser-side WASM dump (uses `Flames\Js`, `Flames\Element`) |

---

## Credits

Based on [php-sage/sage](https://github.com/php-sage/sage), itself a fork of
[Kint](https://github.com/kint-php/kint).
