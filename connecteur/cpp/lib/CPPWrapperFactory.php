<?php

use Monolog\Logger;

class CPPWrapperFactory
{
    public function __construct(
        private readonly CurlWrapperFactory $curlWrapperFactory,
        private readonly MemoryCache $memoryCache,
        private readonly Logger $logger
    ) {
    }

    public function newInstance()
    {
        return new CPPWrapper($this->curlWrapperFactory, $this->memoryCache, $this->logger);
    }
}
