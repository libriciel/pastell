<?php

namespace Pastell\File\Chunk;

use Exception;
use Flow\Basic;
use Flow\Config;
use Flow\FileOpenException;
use Flow\Request;
use Flow\Uploader;

class ChunkUploader
{
    public function __construct(
        private readonly string $upload_chunk_directory,
        private readonly Config $config,
        private Request $request,
    ) {
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getUploadChunkDirectory(): string
    {
        return $this->upload_chunk_directory;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function uploadChunk(string $upload_filepath): bool
    {
        $this->config->setTempDir($this->upload_chunk_directory);
        return Basic::save($upload_filepath, $this->config, $this->request);
    }

    /**
     * @throws Exception
     * @throws FileOpenException
     */
    public function pruneChunks(): void
    {
        if (random_int(1, 100) === 1) {
            Uploader::pruneChunks($this->upload_chunk_directory);
        }
    }
}
