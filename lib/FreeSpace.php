<?php

class FreeSpace
{
    public function getUsagePercent(string $filesystem): float
    {
        $disk_free_space = disk_free_space($filesystem);
        $disk_total_space = disk_total_space($filesystem);
        return ($disk_total_space - $disk_free_space) / $disk_total_space * 100;
    }

    public function getFreeSpace($filesystem): array
    {
        $disk_use_percent = sprintf('%0.2f', $this->getUsagePercent($filesystem));
        $disk_free_space = disk_free_space($filesystem);
        $disk_total_space = disk_total_space($filesystem);
        return [
            'disk_use_space' => $this->human_filesize($disk_total_space - $disk_free_space),
            'disk_total_space' => $this->human_filesize($disk_total_space),
            'disk_use_percent' =>  $disk_use_percent . ' %'
        ];
    }

    //thanks http://jeffreysambells.com/2012/10/25/human-readable-filesize-php
    private function human_filesize($bytes, $decimals = 2)
    {
        $size = ['B','kB','MB','GB','TB','PB','EB','ZB','YB'];
        $factor = intval(floor((strlen($bytes) - 1) / 3));
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . " " . @$size[$factor];
    }
}
