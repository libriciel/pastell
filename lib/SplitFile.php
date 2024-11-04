<?php

class SplitFile
{
    private $logger;

    public function __construct(\Monolog\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $filepath
     * @param $size
     * @param $chunk_name
     * @return array
     * @throws Exception
     */

    public function split($filepath, int $size, $chunk_name)
    {
        $dirname = dirname($filepath);
        $filename = basename($filepath);

        $command = "cd $dirname && split -b $size $filepath $chunk_name";
        $this->logger->debug("Execute shell command", [$command]);
        exec($command, $ouput, $return_var);
        if ($return_var !== 0) {
            $message = "Unable to split $filepath into chunk ";
            $this->logger->error($message);
            throw new Exception($message);
        }

        $this->logger->debug("Execute shell command, result ok", [$command,$ouput]);

        return array_values(array_filter(scandir($dirname), function ($a) use ($chunk_name) {
            return (substr($a, 0, strlen($chunk_name)) == $chunk_name);
        }));
    }
}
