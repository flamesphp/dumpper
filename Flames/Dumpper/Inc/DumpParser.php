<?php

namespace Flames\Dumpper\Inc;

use __PHP_Incomplete_Class;
use ArrayObject;
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
    public static $_level = 0;
    /** @var array<string, true>|null */
    private static $_objects;
    /** @var string|null */
    private static $_marker;

    private static $parsingAlternative = array();
    private static $_placeFullStringInValue = false;

    /**
     * Cached parser instances, keyed by class short-name.
     * Parsers are stateless between parse() calls so reuse is safe.
     *
     * @var array<string, object>
     */
    private static $parserInstances = array();

    /**
     * Cached ReflectionClass instances, keyed by class name.
     *
     * @var array<string, ReflectionClass>
     */
    private static $reflectionClasses = array();

    /**
     * Cached public property names per class: ['ClassName' => ['prop' => true]].
     *
     * @var array<string, array<string, true>>
     */
    private static $classPublicProps = array();

    /** @var bool|null cached result of function_exists('spl_object_hash') */
    private static $hasSplObjectHash = null;

    /**
     * Resets parser state between separate dump() calls.
     *
     * @return void
     */
    public static function reset()
    {
        self::$_level   = 0;
        self::$_objects = self::$_marker = null;
    }

    /**
     * Processes a variable and returns its DumpVariableData representation.
     *
     * @param mixed       $variable
     * @param string|null $name
     * @return DumpVariableData
     */
    final public static function process(&$variable, $name = null)
    {
        $revert = array(
            'level'   => self::$_level,
            'objects' => self::$_objects,
        );

        self::$_level++;

        $varData = new DumpVariableData();
        if (isset($name)) {
            $varData->name = $name;
            if (strlen($varData->name) > 60) {
                $varData->name =
                    DumpHelper::substr($varData->name, 0, 27)
                    . '...'
                    . DumpHelper::substr($varData->name, -28, null);
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

        $varType    = gettype($variable);
        $varType === 'unknown type' and $varType = 'unknown';
        $methodName = '_parse_' . $varType;
        if (!method_exists(__CLASS__, $methodName)) {
            $varData->type = $varType;
            return $varData;
        }

        if (self::$methodName($variable, $varData) === false) {
            self::$_level--;
            return $varData;
        }

        self::$_level   = $revert['level'];
        self::$_objects = $revert['objects'];

        return $varData;
    }

    /**
     * @return bool
     */
    private static function isDepthLimit()
    {
        return Dump::$maxLevels && self::$_level >= Dump::$maxLevels;
    }

    /**
     * Detects whether the array is tabular (array of uniform-key arrays).
     *
     * @param array $variable
     * @return array|false all column keys if tabular, false otherwise
     */
    private static function _isArrayTabular(array $variable)
    {
        if (Dump::enabled() !== Dump::MODE_RICH) {
            return false;
        }

        $arrayKeys   = array();
        $arrayKeySet = array();
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

    /**
     * @param DumpVariableData $varData
     * @return string HTML table cell
     */
    private static function _decorateCell(DumpVariableData $varData)
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

    private static $_dealingWithGlobals = false;

    /**
     * @param array            $variable
     * @param DumpVariableData $variableData
     * @return false|void
     */
    private static function _parse_array(&$variable, DumpVariableData $variableData)
    {
        isset(self::$_marker) or self::$_marker = "\x00" . uniqid();

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
            $firstRow     = true;
            $extendedValue = '<table class="_dumpper-report"><thead>';

            foreach ($variable as $rowIndex => & $row) {
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
            $extendedValue = array();
            foreach ($variable as $key => & $val) {
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
     * @param object           $variable
     * @param DumpVariableData $variableData
     * @return false|void
     */
    private static function _parse_object(&$variable, DumpVariableData $variableData)
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

        if (strpos($variableData->type, "@anonymous\0") !== false) {
            $variableData->type = 'Anonymous class';
        }

        self::$_objects[$hash] = true;

        if (!isset(self::$reflectionClasses[$className])) {
            $rc = new ReflectionClass($className);
            self::$reflectionClasses[$className] = $rc;
            $props = array();
            foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
                $props[$prop->name] = true;
            }
            self::$classPublicProps[$className] = $props;
        }
        $reflector    = self::$reflectionClasses[$className];
        $publicProps  = self::$classPublicProps[$className];

        if (DumpHelper::isHtmlMode() && $reflector->isUserDefined()) {
            $variableData->type = DumpHelper::ideLink(
                $reflector->getFileName(),
                $reflector->getStartLine(),
                $variableData->type
            );
        }
        $variableData->size = 0;

        $extendedValue = array();

        foreach ($castedArray as $key => $value) {
            $output = self::process($value);

            if (is_string($key) && $key[0] === "\x00") {
                $access = $key[1] === '*' ? 'protected' : 'private';
                $key    = substr($key, strrpos($key, "\x00") + 1);
            } else {
                $access = 'public';
                if ($className !== 'stdClass' && !isset($publicProps[$key])) {
                    if ($className !== 'Flames\Collection\Arr') {
                        $access .= ' (dynamically added)';
                    }
                }
            }

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
            if (isset($extendedValue[$name])) {
                if (method_exists($property, 'isReadOnly') && $property->isReadOnly()) {
                    $extendedValue[$name]->access .= ' readonly';
                }
                continue;
            }

            if ($property->isProtected()) {
                $property->setAccessible(true);
                $access = 'protected';
            } elseif ($property->isPrivate()) {
                $property->setAccessible(true);
                $access = 'private';
            } else {
                $access = 'public';
            }

            if (method_exists($property, 'isInitialized') && !$property->isInitialized($variable)) {
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

        if (method_exists($reflector, 'isEnum') && $reflector->isEnum()) {
            $variableData->size  = 'enum';
            $variableData->value = '"' . $variable->name . '"';
        }

        if ($variableData->size) {
            $variableData->extendedValue = $extendedValue;
        }
    }

    private static function _parse_boolean(&$variable, DumpVariableData $variableData)
    {
        $variableData->type  = 'bool';
        $variableData->value = $variable ? 'TRUE' : 'FALSE';
    }

    private static function _parse_double(&$variable, DumpVariableData $variableData)
    {
        $variableData->type  = 'float';
        $variableData->value = $variable;
    }

    private static function _parse_integer(&$variable, DumpVariableData $variableData)
    {
        $variableData->type  = 'integer';
        $variableData->value = $variable;
    }

    private static function _parse_null(&$variable, DumpVariableData $variableData)
    {
        $variableData->type = 'NULL';
    }

    private static function _parse_resource(&$variable, DumpVariableData $variableData)
    {
        $resourceType      = get_resource_type($variable);
        $variableData->type = "resource ({$resourceType})";

        if ($resourceType === 'stream' && $meta = stream_get_meta_data($variable)) {
            if (isset($meta['uri'])) {
                $file = $meta['uri'];
                if (function_exists('stream_is_local') && stream_is_local($file)) {
                    $file = DumpHelper::shortenPath($file);
                }
                $variableData->value = $file;
            }
        }
    }

    private static function _parse_string(&$variable, DumpVariableData $variableData)
    {
        if (preg_match('//u', $variable)) {
            $variableData->type = 'string';
        } else {
            $variableData->type .= 'binary string';
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

    private static function _parse_unknown(&$variable, DumpVariableData $variableData)
    {
        $type = gettype($variable);
        $variableData->type  = 'UNKNOWN' . (!empty($type) ? " ({$type})" : '');
        $variableData->value = var_export($variable, true);
    }

    /**
     * @param object $variable
     * @return string
     */
    private static function getObjectHash($variable)
    {
        if (self::$hasSplObjectHash === null) {
            self::$hasSplObjectHash = function_exists('spl_object_hash');
        }
        if (self::$hasSplObjectHash) {
            return spl_object_hash($variable);
        }

        ob_start();
        var_dump($variable);
        preg_match('[#(\d+)]', ob_get_clean(), $match);

        return $match[1];
    }

    /**
     * Parses $alternativesArray as a context-aware alternative view for $originalVar.
     *
     * @param mixed        $originalVar
     * @param array|object $alternativesArray
     * @return DumpVariableData[]|string|null
     */
    public static function alternativesParse($originalVar, $alternativesArray)
    {
        $varData = new DumpVariableData();

        if (is_object($originalVar)) {
            self::$_objects[self::getObjectHash($originalVar)] = true;
        } elseif (is_array($originalVar)) {
            isset(self::$_marker) or self::$_marker = "\x00" . uniqid();
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
