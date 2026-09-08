<?php

class SplitFile
{
    private $logger;

    public function __construct(\Monolog\Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function split($filepath, int $size, $chunk_name): array
    {
        $dirname = dirname($filepath);

        $command = "cd $dirname && split -a 6 -b $size $filepath $chunk_name";
        $this->logger->debug('Execute shell command', [$command]);
        exec($command, $ouput, $return_var);
        if ($return_var !== 0) {
            $message = "Unable to split $filepath into chunk ";
            $this->logger->error($message);
            throw new \RuntimeException($message);
        }

        $this->logger->debug('Execute shell command, result ok', [$command,$ouput]);

        return array_values(array_filter(scandir($dirname), static function ($a) use ($chunk_name) {
            return (str_starts_with($a, $chunk_name));
        }));
    }
}
