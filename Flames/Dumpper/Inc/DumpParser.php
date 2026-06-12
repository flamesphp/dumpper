<?php
declare(strict_types=1);


namespace Flames\Dumpper\Inc;

use __PHP_Incomplete_Class;
use ArrayObject;
use Flames\Dumpper\Attribute\Hidden;
use Flames\Dumpper\Decorators\DumpDecoratorsRich;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpVariableData;
use Flames\Dumpper\Dump;
use ReflectionClass;
use ReflectionProperty;

/**
 * Recursively parses any PHP variable into a DumpVariableData tree.
 *
 * @internal
 */
class DumpParser
{
    public static int $_level = 0;

    /** @var array<int, true>|null */
    private static ?array $_objects = null;

    private static ?string $_marker = null;

    /** @var array<string, true> */
    private static array $parsingAlternative = [];

    private static bool $_placeFullStringInValue = false;

    /**
     * Cached parser instances, keyed by class short-name.
     *
     * @var array<string, object>
     */
    private static array $parserInstances = [];

    /**
     * Map of PHP gettype() return values to their _parse_* method names.
     *
     * @var array<string, string>
     */
    private static array $parseMethods = [
        'array'   => '_parse_array',
        'object'  => '_parse_object',
        'boolean' => '_parse_boolean',
        'double'  => '_parse_double',
        'integer' => '_parse_integer',
        'NULL'    => '_parse_null',
        'resource' => '_parse_resource',
        'string'  => '_parse_string',
        'unknown' => '_parse_unknown',
    ];

    /**
     * Cached ReflectionClass instances, keyed by class name.
     *
     * @var array<string, ReflectionClass>
     */
    private static array $reflectionClasses = [];

    /**
     * Cached public property names per class: ['ClassName' => ['prop' => true]].
     *
     * @var array<string, array<string, true>>
     */
    private static array $classPublicProps = [];

    /**
     * Cached hidden property names per class: ['ClassName' => ['prop' => true]].
     *
     * @var array<string, array<string, true>>
     */
    private static array $classHiddenProps = [];

    private static bool $_dealingWithGlobals = false;

    /**
     * Resets parser state between separate dump() calls.
     */
    public static function reset(): void
    {
        self::$_level   = 0;
        self::$_objects = self::$_marker = null;
    }

    /**
     * Processes a variable and returns its DumpVariableData representation.
     */
    final public static function process(mixed &$variable, string|int|null $name = null): DumpVariableData
    {
        $revert = [
            'level'   => self::$_level,
            'objects' => self::$_objects,
        ];

        self::$_level++;

        $varData = new DumpVariableData();
        if (isset($name)) {
            $varData->name = $name;
            if (is_string($name) && strlen($name) > 60) {
                $varData->name =
                    DumpHelper::substr($name, 0, 27)
                    . '...'
                    . DumpHelper::substr($name, -28, null);
            }
        }

        foreach (Dump::$enabledParsers as $parserClass => $enabled) {
            if (!$enabled || isset(self::$parsingAlternative[$parserClass])) {
                continue;
            }
            self::$parsingAlternative[$parserClass] = true;

            if (!isset(self::$parserInstances[$parserClass])) {
                self::$parserInstances[$parserClass] = new ('Flames\Dumpper\Parsers\\' . $parserClass)();
            }
            $parser      = self::$parserInstances[$parserClass];
            $parseResult = $parser->parse($variable, $varData);

            if ($parseResult !== false && $parser->replacesAllOtherParsers()) {
                unset(self::$parsingAlternative[$parserClass]);
                self::$_level   = $revert['level'];
                self::$_objects = $revert['objects'];
                return $varData;
            }
            unset(self::$parsingAlternative[$parserClass]);
        }

        $varType = gettype($variable);
        $varType = $varType === 'unknown type' ? 'unknown' : $varType;

        if (!isset(self::$parseMethods[$varType])) {
            $varData->type = $varType;
            return $varData;
        }

        $methodName = self::$parseMethods[$varType];

        if (self::$methodName($variable, $varData) === false) {
            self::$_level--;
            return $varData;
        }

        self::$_level   = $revert['level'];
        self::$_objects = $revert['objects'];

        return $varData;
    }

    private static function isDepthLimit(): bool
    {
        return (bool)(Dump::$maxLevels && self::$_level >= Dump::$maxLevels);
    }

    /**
     * Detects whether the array is tabular (array of uniform-key arrays).
     *
     * @return array|false all column keys if tabular, false otherwise
     */
    private static function _isArrayTabular(array $variable): array|false
    {
        if (Dump::enabled() !== Dump::MODE_RICH) {
            return false;
        }

        $arrayKeys   = [];
        $arrayKeySet = [];
        $keys        = null;
        $closeEnough = false;

        foreach ($variable as $k => $row) {
            if (isset(self::$_marker) && $k === self::$_marker) {
                continue;
            }

            if (!is_array($row) || empty($row)) {
                return false;
            }

            foreach ($row as $col) {
                if (!empty($col) && !is_scalar($col)) {
                    return false;
                }
            }

            if (isset($keys) && !$closeEnough) {
                if ($keys !== array_keys($row)) {
                    return false;
                }
                $closeEnough = true;
            } else {
                $keys = array_keys($row);
            }

            foreach ($keys as $ak) {
                if (!isset($arrayKeySet[$ak])) {
                    $arrayKeySet[$ak] = true;
                    $arrayKeys[]      = $ak;
                }
            }
        }

        return $arrayKeys;
    }

    private static function _decorateCell(DumpVariableData $varData): string
    {
        if ($varData->extendedValue !== null) {
            return '<td>' . DumpDecoratorsRich::decorate($varData) . '</td>';
        }

        $output = '<td';

        if ($varData->value !== null) {
            $output .= ' title="' . $varData->type;
            if ($varData->size !== null) {
                $output .= ' (' . $varData->size . ')';
            }
            $output .= '">' . $varData->value;
        } else {
            $output .= '>';
            if ($varData->type !== 'NULL') {
                $output .= '<u>' . $varData->type;
                if ($varData->size !== null) {
                    $output .= '(' . $varData->size . ')';
                }
                $output .= '</u>';
            } else {
                $output .= '<u>NULL</u>';
            }
        }

        return $output . '</td>';
    }

    /**
     * @return false|void
     */
    private static function _parse_array(mixed &$variable, DumpVariableData $variableData)
    {
        self::$_marker ??= "\x00" . uniqid();

        $globalsDetector = false;
        if (array_key_exists('GLOBALS', $variable) && is_array($variable['GLOBALS'])) {
            $globalsDetector = "\x01" . uniqid();
            $variable['GLOBALS'][$globalsDetector] = true;
            if (isset($variable[$globalsDetector])) {
                unset($variable[$globalsDetector]);
                self::$_dealingWithGlobals = true;
            } else {
                unset($variable['GLOBALS'][$globalsDetector]);
                $globalsDetector = false;
            }
        }

        $variableData->type = 'array';
        $variableData->size = count($variable);

        if ($variableData->size === 0) {
            return;
        }
        if (isset($variable[self::$_marker])) {
            if (self::$_dealingWithGlobals) {
                $variableData->value = '*RECURSION*';
            } else {
                unset($variable[self::$_marker]);
                $variableData->value = self::$_marker;
            }
            return false;
        }
        if (self::isDepthLimit()) {
            $variableData->extendedValue = '*DEPTH TOO GREAT*';
            return false;
        }

        $isSequential = DumpHelper::isArraySequential($variable);
        $variable[self::$_marker] = true;

        if ($variableData->size > 1 && ($arrayKeys = self::_isArrayTabular($variable)) !== false) {
            $firstRow      = true;
            $extendedValue = '<table class="_dumpper-report"><thead>';

            foreach ($variable as $rowIndex => &$row) {
                self::$_placeFullStringInValue = true;

                if ($rowIndex === self::$_marker) {
                    continue;
                }
                if (isset($row[self::$_marker])) {
                    $variableData->value = '*RECURSION*';
                    return false;
                }

                $extendedValue .= '<tr>';
                if ($isSequential) {
                    $output = '<td>' . (((int)$rowIndex) + 1) . '</td>';
                } else {
                    $output = self::_decorateCell(self::process($rowIndex));
                }
                if ($firstRow) {
                    $extendedValue .= '<th>&nbsp;</th>';
                }

                foreach ($arrayKeys as $key) {
                    if ($firstRow) {
                        $extendedValue .= '<th>' . DumpHelper::esc($key) . '</th>';
                    }
                    if (in_array($key, Dump::$arrayKeysBlacklist, true)) {
                        $output .= '<td class="_dumpper-empty"><u>*REDACTED*</u></td>';
                        continue;
                    }
                    if (!array_key_exists($key, $row)) {
                        $output .= '<td class="_dumpper-empty"></td>';
                        continue;
                    }
                    $var = self::process($row[$key]);
                    if ($var->value === self::$_marker) {
                        $variableData->value = '*RECURSION*';
                        return false;
                    } elseif ($var->value === '*RECURSION*') {
                        $output .= '<td class="_dumpper-empty"><u>*RECURSION*</u></td>';
                    } else {
                        $output .= self::_decorateCell($var);
                    }
                    unset($var);
                }

                if ($firstRow) {
                    $extendedValue .= '</tr></thead><tr>';
                    $firstRow = false;
                }
                $extendedValue .= $output . '</tr>';
            }
            self::$_placeFullStringInValue = false;
            $variableData->extendedValue = $extendedValue . '</table>';
        } else {
            $extendedValue = [];
            foreach ($variable as $key => &$val) {
                if ($key === self::$_marker) {
                    continue;
                }
                if (in_array($key, Dump::$arrayKeysBlacklist, true)) {
                    $val = '*REDACTED*';
                }
                $output = self::process($val);
                if ($output->value === self::$_marker) {
                    $variableData->value = '*RECURSION*';
                    return false;
                }
                if ($isSequential) {
                    $output->name = null;
                } else {
                    $output->operator = '=>';
                    $output->name     = is_int($key) ? $key : "'" . $key . "'";
                }
                $extendedValue[] = $output;
            }
            $variableData->extendedValue = $extendedValue;
        }

        if ($globalsDetector) {
            self::$_dealingWithGlobals = false;
        }

        unset($variable[self::$_marker]);
    }

    /**
     * @return false|void
     */
    private static function _parse_object(mixed &$variable, DumpVariableData $variableData)
    {
        $hash        = self::getObjectHash($variable);
        $castedArray = (array)$variable;
        $className   = get_class($variable);

        $variableData->type = $className;
        $variableData->size = count($castedArray);

        if (isset(self::$_objects[$hash])) {
            return false;
        }
        if (self::isDepthLimit()) {
            $variableData->extendedValue = '*DEPTH TOO GREAT*';
            return false;
        }

        if ($variableData->type === 'ArrayObject' || is_subclass_of($variable, 'ArrayObject')) {
            $arrayObjectFlags = $variable->getFlags();
            $variable->setFlags(ArrayObject::STD_PROP_LIST);
        }

        if (str_contains($variableData->type, "@anonymous\0")) {
            $variableData->type = 'Anonymous class';
        }

        self::$_objects[$hash] = true;

        if (!isset(self::$reflectionClasses[$className])) {
            $rc = new ReflectionClass($className);
            self::$reflectionClasses[$className] = $rc;
            $props       = [];
            $hiddenProps = [];
            foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                $props[$prop->name] = true;
            }
            foreach ($rc->getProperties() as $prop) {
                if (self::isPropertyHidden($prop)) {
                    $hiddenProps[$prop->name] = true;
                }
            }
            self::$classPublicProps[$className] = $props;
            self::$classHiddenProps[$className] = $hiddenProps;
        }
        $reflector   = self::$reflectionClasses[$className];
        $publicProps = self::$classPublicProps[$className];
        $hiddenProps = self::$classHiddenProps[$className];

        if (DumpHelper::isHtmlMode() && $reflector->isUserDefined()) {
            $variableData->type = DumpHelper::ideLink(
                $reflector->getFileName(),
                $reflector->getStartLine(),
                $variableData->type
            );
        }
        $variableData->size = 0;

        $extendedValue = [];

        foreach ($castedArray as $key => $value) {
            $propName = $key;
            if (is_string($key) && $key[0] === "\x00") {
                $access   = $key[1] === '*' ? 'protected' : 'private';
                $propName = substr($key, strrpos($key, "\x00") + 1);
                $key      = $propName;
            } else {
                $access = 'public';
                if ($className !== 'stdClass' && !isset($publicProps[$key])) {
                    if ($className !== 'Flames\Collection\Arr') {
                        $access .= ' (dynamically added)';
                    }
                }
            }

            if (isset($hiddenProps[$propName])) {
                continue;
            }

            $output           = self::process($value);
            $output->name     = DumpHelper::esc($key);
            $output->access   = $access;
            $output->operator = '->';
            $extendedValue[$key] = $output;
            $variableData->size++;
        }

        if ($variable instanceof __PHP_Incomplete_Class) {
            $variableData->extendedValue = $extendedValue;
            return $castedArray;
        }

        foreach ($reflector->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();
            if (isset($hiddenProps[$name])) {
                continue;
            }
            if (isset($extendedValue[$name])) {
                if ($property->isReadOnly()) {
                    $extendedValue[$name]->access .= ' readonly';
                }
                continue;
            }

            if ($property->isProtected()) {
                $access = 'protected';
            } elseif ($property->isPrivate()) {
                $access = 'private';
            } else {
                $access = 'public';
            }

            if (!$property->isInitialized($variable)) {
                $value  = null;
                $access .= ' [uninitialized]';
            } else {
                $value = $property->getValue($variable);
            }

            $output           = self::process($value, DumpHelper::esc($name));
            $output->access   = $access;
            $output->operator = '->';
            $extendedValue[]  = $output;
            $variableData->size++;
        }

        if (isset($arrayObjectFlags)) {
            $variable->setFlags($arrayObjectFlags);
        }

        if ($reflector->isEnum()) {
            $variableData->size  = 'enum';
            $variableData->value = '"' . $variable->name . '"';
        }

        if ($variableData->size) {
            $variableData->extendedValue = $extendedValue;
        }
    }

    private static function _parse_boolean(mixed &$variable, DumpVariableData $variableData): void
    {
        $variableData->type  = 'bool';
        $variableData->value = $variable ? 'TRUE' : 'FALSE';
    }

    private static function _parse_double(mixed &$variable, DumpVariableData $variableData): void
    {
        $variableData->type  = 'float';
        $variableData->value = $variable;
    }

    private static function _parse_integer(mixed &$variable, DumpVariableData $variableData): void
    {
        $variableData->type  = 'integer';
        $variableData->value = $variable;
    }

    private static function _parse_null(mixed &$variable, DumpVariableData $variableData): void
    {
        $variableData->type = 'NULL';
    }

    private static function _parse_resource(mixed &$variable, DumpVariableData $variableData): void
    {
        $resourceType       = get_resource_type($variable);
        $variableData->type = "resource ({$resourceType})";

        if ($resourceType === 'stream' && $meta = stream_get_meta_data($variable)) {
            if (isset($meta['uri'])) {
                $file = $meta['uri'];
                if (stream_is_local($file)) {
                    $file = DumpHelper::shortenPath($file);
                }
                $variableData->value = $file;
            }
        }
    }

    private static function _parse_string(mixed &$variable, DumpVariableData $variableData): void
    {
        if (preg_match('//u', $variable)) {
            $variableData->type = 'string';
        } else {
            $variableData->type = 'binary string';
        }

        $encoding = DumpHelper::detectEncoding($variable);
        if ($encoding !== 'UTF-8') {
            $variableData->type .= ' ' . $encoding;
        }

        $variableData->size = DumpHelper::strlen($variable, $encoding);

        if (self::$_placeFullStringInValue) {
            $variableData->value = DumpHelper::esc($variable);
        } elseif (!DumpHelper::isRichMode()) {
            $variableData->value = '"' . DumpHelper::esc($variable) . '"';
        } else {
            $decoded = DumpHelper::esc($variable);

            if ($variableData->size > (DumpHelper::MAX_STR_LENGTH + 8)) {
                $variableData->value =
                    '"'
                    . DumpHelper::esc(
                        DumpHelper::substr($variable, 0, DumpHelper::MAX_STR_LENGTH, $encoding),
                        false
                    )
                    . '&hellip;"';
            } else {
                $variableData->value = '"' . DumpHelper::esc($variable, false) . '"';
            }

            if ($variable !== preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $variable)) {
                $variableData->extendedValue = DumpHelper::esc($variable, false);
                $variableData->addTabToView($variable, 'Hidden characters escaped', $decoded);
            } elseif (
                $variableData->size > (DumpHelper::MAX_STR_LENGTH + 8)
                || $variable !== preg_replace('/\s+/', ' ', $variable)
            ) {
                $variableData->extendedValue = $decoded;
            }
        }
    }

    private static function _parse_unknown(mixed &$variable, DumpVariableData $variableData): void
    {
        $type = gettype($variable);
        $variableData->type  = 'UNKNOWN' . (!empty($type) ? " ({$type})" : '');
        $variableData->value = var_export($variable, true);
    }

    private static function getObjectHash(object $variable): int
    {
        return spl_object_id($variable);
    }

    private static function isPropertyHidden(ReflectionProperty $property): bool
    {
        return $property->getAttributes(Hidden::class) !== [];
    }

    /**
     * Parses $alternativesArray as a context-aware alternative view for $originalVar.
     *
     * @return DumpVariableData[]|string|null
     */
    public static function alternativesParse(mixed $originalVar, array|object $alternativesArray): array|string|null
    {
        $varData = new DumpVariableData();

        if (is_object($originalVar)) {
            self::$_objects[self::getObjectHash($originalVar)] = true;
        } elseif (is_array($originalVar)) {
            self::$_marker ??= "\x00" . uniqid();
            $originalVar[self::$_marker] = true;
        }

        if (is_array($alternativesArray)) {
            self::_parse_array($alternativesArray, $varData);
        } else {
            self::_parse_object($alternativesArray, $varData);
        }

        return $varData->extendedValue;
    }
}
