<?php
declare(strict_types=1);


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
     */
    public function decorate(DumpVariableData $varData): string;

    /**
     * Renders a full backtrace.
     *
     * @param DumpTraceStep[] $traceData
     */
    public function decorateTrace(array $traceData, bool $pathsOnly = false): string;

    /**
     * Opens the outer wrapper element for a single dump() call.
     */
    public function wrapStart(): string;

    /**
     * Closes the outer wrapper and appends callee information.
     */
    public function wrapEnd(array $callee, array $miniTrace, array $prevCaller): string;

    /**
     * Returns the CSS/JS assets required for the first render (may be empty string).
     */
    public function init(): string;
}
