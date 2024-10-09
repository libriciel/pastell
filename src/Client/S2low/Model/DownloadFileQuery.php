<?php

declare(strict_types=1);

namespace Pastell\Client\S2low\Model;

class DownloadFileQuery
{
    public string $file;
    public bool $tampon;
    public ?string $date_affichage;
}
