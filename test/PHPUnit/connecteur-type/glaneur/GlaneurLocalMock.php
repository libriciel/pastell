<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class GlaneurLocalMock extends GlaneurConnecteur
{
    protected function listFile(string $directory): array
    {
        $finder = new Finder();
        $iter = $finder->in($directory);
        $result['count'] = $iter->count();
        $iterator = new LimitIterator($iter->getIterator(), 0, self::NB_MAX_FILE_DISPLAY);

        $result['detail'] = "";

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($iterator as $file) {
            $result['detail'] .= $file->getBasename() . " - " . $file->getSize() . " octets  - " . date("Y-m-d H:i:s", $file->getCTime()) . "\n";
        }

        return $result;
    }


    protected function listAllFile(string $directory): array
    {
        $result = [];
        $finder = new Finder();
        $iter = $finder->in($directory);
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($iter as $file) {
            $result[] = $file->getBasename();
        }
        return $result;
    }


    protected function getNextItem(string $directory): ?string
    {
        $finder = new Finder();
        $found = $finder->in($directory);

        /** @var Symfony\Component\Finder\SplFileInfo $file */
        foreach ($found as $file) {
            return $file->getBasename();
        }
        return null;
    }

    protected function isDir(string $directory_or_file): bool
    {
        return is_dir($directory_or_file);
    }

    protected function mirror(string $directory, string $tmp_folder)
    {
        $filesystem = new Filesystem();
        $filesystem->mirror($directory, $tmp_folder);
    }

    protected function remove(array $item_list)
    {
        $filesystem = new Filesystem();
        $filesystem->remove($item_list);
    }

    protected function exists(string $file_or_directory): bool
    {
        $filesystem = new Filesystem();
        return $filesystem->exists($file_or_directory);
    }

    protected function rename(string $item, string $file_deplacement)
    {
        $filesystem = new Filesystem();
        $filesystem->rename($item, $file_deplacement);
    }

    protected function copy(string $originFile, string $targetFile)
    {
        $filesystem = new Filesystem();
        $filesystem->copy($originFile, $targetFile);
    }
}
