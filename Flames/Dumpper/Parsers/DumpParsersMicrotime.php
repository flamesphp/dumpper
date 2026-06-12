<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Parsers\DumpParserInterface;

/**
 * Benchmarks consecutive microtime() calls, tracking laps and memory usage.
 *
 * @internal
 */
class DumpParsersMicrotime implements DumpParserInterface
{
    private static array $times = [];
    private static array $laps  = [];

    public function replacesAllOtherParsers(): bool
    {
        return false;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (
            !is_string($variable)
            || !preg_match('/^0\.[\d]{8} [\d]{10}$/', $variable)
        ) {
            return false;
        }

        [$usec, $sec] = explode(' ', $variable);

        $time = (float)$usec + (float)$sec;

        $size        = memory_get_usage(true);
        $unit        = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i           = (int)floor(log($size, 1024));
        $memoryUsage = round($size / (1024 ** $i), 3) . $unit[$i];

        $numberOfCalls = count(self::$times);
        if ($numberOfCalls > 0) {
            $lap          = $time - end(self::$times);
            self::$laps[] = $lap;
            $sinceLast    = round($lap, 4) . 's.';

            $sinceStart      = null;
            $averageDuration = null;
            if ($numberOfCalls > 1) {
                $sinceStart      = round($time - self::$times[0], 4) . 's.';
                $averageDuration = round(array_sum(self::$laps) / $numberOfCalls, 4) . 's.';
            }

            if (DumpHelper::isRichMode()) {
                $tabContents = "<b>SINCE LAST SUCH CALL:</b> <b class=\"_dumpper-microtime\">" . round($lap, 4) . '</b>s.';
                if ($numberOfCalls > 1) {
                    $tabContents .= "\n<b>SINCE START:</b> {$sinceStart}";
                    $tabContents .= "\n<b>AVERAGE DURATION:</b> {$averageDuration}";
                }
                $tabContents .= "\n<b>PHP MEMORY USAGE:</b> {$memoryUsage}";
                $varData->addTabToView($variable, 'Benchmark', $tabContents);
            } else {
                $varData->extendedValue = ['Since last such call' => $sinceLast];
                if ($sinceStart !== null) {
                    $varData->extendedValue['Since start']      = $sinceStart;
                    $varData->extendedValue['Average duration'] = $averageDuration;
                }
                $varData->extendedValue['Memory usage'] = $memoryUsage;
            }
        } else {
            $varData->extendedValue = [
                'Time (from microtime)' => @date('Y-m-d H:i:s', (int)$sec) . substr($usec, 1),
                'PHP MEMORY USAGE'      => $memoryUsage,
            ];
        }

        self::$times[] = $time;
        return null;
    }
}
