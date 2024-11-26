<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

final class File
{
    public string $id;
    public string $name;
    public string $postedFilename;
    public string $mimetype;
    public string $size;
    public string $sign;
    public string $codePj;
}
