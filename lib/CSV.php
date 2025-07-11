<?php

class CSV
{
    public function get($file_path, $delimiter): array
    {
        $file = $this->openFile($file_path);
        if (! $file) {
            return [];
        }

        $result = [];
        while (($data = fgetcsv($file, 1000, $delimiter, escape: '')) !== false) {
            $result[] = $data ;
        }
        fclose($file);
        return $result;
    }

    private function openFile($file_path)
    {
        if (! file_exists($file_path)) {
            return false;
        }
        $fileInfo = new finfo();
        $info = $fileInfo->file($file_path, FILEINFO_MIME_TYPE);
        if (in_array($info, ['application/x-gzip', 'application/gzip'])) {
            return gzopen($file_path, "r");
        }
        return fopen($file_path, "r");
    }
}
