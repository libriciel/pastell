<?php

/**
 * MemoryCache used by the test suite. flushAll() removes computed caches
 * (pack- and database-dependent lists, vfs:// files) but keeps the YML parse
 * cache of source definition files: they are immutable during a test run,
 * YMLLoader checks their mtime anyway, and re-parsing them costs several
 * hundred milliseconds per test.
 */
class StaticWrapperKeepingSourceYmlFiles extends StaticWrapper
{
    public function flushAll()
    {
        foreach (array_keys(self::$memory) as $id) {
            if (!$this->isSourceYmlFileCacheKey((string)$id)) {
                $this->delete($id);
            }
        }
    }

    private function isSourceYmlFileCacheKey(string $id): bool
    {
        foreach ([YMLLoader::CACHE_PREFIX, YMLLoader::CACHE_PREFIX_MTIME] as $prefix) {
            if (str_starts_with($id, $prefix)) {
                $filepath = substr($id, strlen($prefix));
                return str_starts_with($filepath, PASTELL_PATH . '/')
                    && !str_starts_with($filepath, PASTELL_PATH . '/data');
            }
        }
        return false;
    }
}
