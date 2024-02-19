<?php

namespace Pastell\File\Chunk;

use Flow\File;
use Flow\Request;

class ChunkRequest
{
    private string $file_name;
    private int $file_number = 0;
    private int $chunk_number;
    private int $total_chunks;
    private int $current_chunk_size;
    private int $total_size;
    private array $file;

    public function __construct()
    {
        $fields = ['file_name', 'chunk_number', 'total_chunks', 'total_size'];

        foreach ($fields as $field) {
            if (!isset($_REQUEST[$field])) {
                throw new \InvalidArgumentException('Champ ' . $field . ' manquant ou vide.');
            }
        }

        if (!isset($_FILES['file'])) {
            throw new \InvalidArgumentException('Fichier manquant.');
        }

        if (!is_numeric($_REQUEST['total_chunks'])) {
            throw new \InvalidArgumentException('Le champ total_chunks doit être une valeur numérique.');
        }

        if (!is_numeric($_REQUEST['chunk_number']) || $_REQUEST['chunk_number'] > $_REQUEST['total_chunks']) {
            throw new \InvalidArgumentException('Le champ chunk_number doit être inférieur ou égal à total_chunks.');
        }

        $this->file_name = $_REQUEST['file_name'];
        $this->chunk_number = $_REQUEST['chunk_number'];
        $this->total_chunks = $_REQUEST['total_chunks'];
        $this->current_chunk_size = $_FILES['file']['size'];
        $this->total_size = $_REQUEST['total_size'];
        $this->file = $_FILES['file'];
    }

    public function getFileName(): string
    {
        return $this->file_name;
    }

    public function setFileNumber(int $file_number): void
    {
        $this->file_number = $file_number;
    }

    public function getFileNumber(): int
    {
        return $this->file_number;
    }

    public function getChunkNumber(): int
    {
        return $this->chunk_number;
    }

    public function getTotalChunks(): int
    {
        return $this->total_chunks;
    }

    public function getCurrentChunkSize(): int
    {
        return $this->current_chunk_size;
    }

    public function getTotalSize(): int
    {
        return $this->total_size;
    }

    public function getRequest(): Request
    {
        $params = [
            'file_name' => $this->getFileName(),
            'flowChunkNumber' => $this->getChunkNumber(),
            'flowTotalChunks' => $this->getTotalChunks(),
            'flowCurrentChunkSize' => $this->getCurrentChunkSize(),
            'flowTotalSize' => $this->getTotalSize(),
            'flowIdentifier' => $this->getFileName() . $this->getFileNumber(),
        ];
        return new Request($params, $this->file);
    }
}
