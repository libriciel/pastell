<?php

namespace Pastell\Client\S2low\Responses;

class File
{
    public function __construct(
        public string $id,
        public string $name,
        public string $posted_filename,
        public string $mimetype,
        public string $size,
        public string $signature
    ) {
    }
}
