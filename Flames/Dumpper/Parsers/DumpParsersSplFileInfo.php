<?php
declare(strict_types=1);


namespace Flames\Dumpper\Parsers;

use Exception;
use FilesystemIterator;
use Flames\Dumpper\Inc\DumpHelper;
use Flames\Dumpper\Inc\DumpVariableData;
use Flames\Dumpper\Parsers\DumpParserInterface;
use SplFileInfo;
use SplFileObject;

/**
 * Renders detailed file/directory metadata for SplFileInfo instances.
 *
 * @internal
 */
class DumpParsersSplFileInfo implements DumpParserInterface
{
    public function replacesAllOtherParsers(): bool
    {
        return true;
    }

    public function parse(mixed &$variable, mixed $varData): mixed
    {
        if (!$variable instanceof SplFileInfo || $variable instanceof SplFileObject) {
            return false;
        }

        return $this->run($variable, $varData, $variable);
    }

    /**
     * Shared logic for DumpParsersSplFileInfo and DumpParsersFilePath.
     */
    protected function run(mixed &$variable, mixed $varData, SplFileInfo $fileInfo): mixed
    {
        $varData->value = '"' . DumpHelper::esc($fileInfo->getPathname()) . '"';
        $varData->type  = get_class($fileInfo);

        if (!$fileInfo->getPathname() || !$fileInfo->getRealPath()) {
            $varData->size = 'invalid path';
            return true;
        }

        try {
            $flags = [];
            $perms = $fileInfo->getPerms();

            if (($perms & 0xC000) === 0xC000)     { $type = 'File socket';            $flags[] = 's'; }
            elseif (($perms & 0xA000) === 0xA000) { $type = 'File symlink';           $flags[] = 'l'; }
            elseif (($perms & 0x8000) === 0x8000) { $type = 'File';                   $flags[] = '-'; }
            elseif (($perms & 0x6000) === 0x6000) { $type = 'Block special file';     $flags[] = 'b'; }
            elseif (($perms & 0x4000) === 0x4000) { $type = 'Directory';              $flags[] = 'd'; }
            elseif (($perms & 0x2000) === 0x2000) { $type = 'Character special file'; $flags[] = 'c'; }
            elseif (($perms & 0x1000) === 0x1000) { $type = 'FIFO pipe file';         $flags[] = 'p'; }
            else                                   { $type = 'Unknown file';           $flags[] = 'u'; }

            $flags[] = (($perms & 0x0100) ? 'r' : '-');
            $flags[] = (($perms & 0x0080) ? 'w' : '-');
            $flags[] = (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x') : (($perms & 0x0800) ? 'S' : '-'));
            $flags[] = (($perms & 0x0020) ? 'r' : '-');
            $flags[] = (($perms & 0x0010) ? 'w' : '-');
            $flags[] = (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x') : (($perms & 0x0400) ? 'S' : '-'));
            $flags[] = (($perms & 0x0004) ? 'r' : '-');
            $flags[] = (($perms & 0x0002) ? 'w' : '-');
            $flags[] = (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x') : (($perms & 0x0200) ? 'T' : '-'));

            $varData->type = get_class($fileInfo);

            if ($type === 'Directory') {
                $name = 'Existing Directory';
                $size = iterator_count(
                    new FilesystemIterator($fileInfo->getRealPath(), FilesystemIterator::SKIP_DOTS)
                ) . ' item(s)';
            } else {
                $name = "Existing {$type}";
                $size = $this->humanFilesize($fileInfo->getSize());
            }

            $extra = [];
            if ($fileInfo->getRealPath() !== $fileInfo->getPathname()) {
                $extra['realPath'] = $fileInfo->getRealPath();
            }

            if (DumpHelper::isRichMode()) {
                $extra['flags'] = implode($flags);
                if ($fileInfo->getGroup() || $fileInfo->getOwner()) {
                    $extra['group&owner'] = $fileInfo->getGroup() . ':' . $fileInfo->getOwner();
                }
                $extra['created']  = date('Y-m-d H:i:s', $fileInfo->getCTime());
                $extra['modified'] = date('Y-m-d H:i:s', $fileInfo->getMTime());
                $extra['accessed'] = date('Y-m-d H:i:s', $fileInfo->getATime());
                if ($fileInfo->isLink()) {
                    $extra['link']       = 'true';
                    $extra['linkTarget'] = $fileInfo->getLinkTarget();
                }
                $varData->addTabToView($variable, $name . " [{$size}]", $extra);
            } else {
                $varData->extendedValue = [$name => $size] + $extra;
            }
        } catch (Exception) {
            return false;
        }

        return true;
    }

    private function humanFilesize(int $bytes): string
    {
        if ($bytes < 10240) {
            return "{$bytes} bytes";
        }

        $sizeInBytes     = $bytes;
        $units           = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $precisionByUnit = [0, 1, 1, 2, 2, 3, 3, 4, 4];

        for ($order = 0; ($bytes / 1024) >= 0.9 && $order < count($units); $order++) {
            $bytes /= 1024;
        }

        return $sizeInBytes . ' bytes (' . round($bytes, $precisionByUnit[$order]) . $units[$order] . ')';
    }
}
