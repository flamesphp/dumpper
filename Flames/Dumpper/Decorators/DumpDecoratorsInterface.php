<?php

namespace Flames\Dumpper\Decorators;

use Flames\Dumpper\Inc\DumpTraceStep;
use Flames\Dumpper\Inc\DumpVariableData;

/**
 * Contract for all output decorators (Rich HTML, Plain text, CLI).
 *
 * @internal
 */
interface DumpDecoratorsInterface
{
    /**
     * Renders a single DumpVariableData node.
     *
     * @param DumpVariableData $varData
     * @return string
     */
    public function decorate(DumpVariableData $varData);

    /**
     * Renders a full backtrace.
     *
     * @param DumpTraceStep[] $traceData
     * @param bool            $pathsOnly skip arguments and callee objects
     * @return string
     */
    public function decorateTrace(array $traceData, $pathsOnly = false);

    /**
     * Opens the outer wrapper element for a single dump() call.
     *
     * @return string
     */
    public function wrapStart();

    /**
     * Closes the outer wrapper and appends callee information.
     *
     * @param array $callee
     * @param array $miniTrace
     * @param array $prevCaller
     * @return string
     */
    public function wrapEnd($callee, $miniTrace, $prevCaller);

    /**
     * Returns the CSS/JS assets required for the first render (may be empty string).
     *
     * @return string
     */
    public function init();
}
