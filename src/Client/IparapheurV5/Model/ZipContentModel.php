<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

class ZipContentModel
{
    public string $name;
    public string $id;
    public string $premisFile;
    /** @var string[] */
    public array $documentPrincipaux = [];
    public string $bordereau;
    /** @var string[]  */
    public array $annexe = [];
}
