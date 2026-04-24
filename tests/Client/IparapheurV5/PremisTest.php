<?php

declare(strict_types=1);

namespace Pastell\Tests\Client\IparapheurV5;

use FileToSign;
use Fichier;
use Pastell\Client\IparapheurV5\Model\Premis;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PremisTest extends TestCase
{
    public function testEmptyDossierTitreThrows(): void
    {
        $fileToSign = new FileToSign();
        $fileToSign->dossierTitre = '';
        $fileToSign->document = new Fichier();
        $fileToSign->document->filename = 'doc.pdf';

        $this->expectException(RuntimeException::class);
        Premis::fromFileToSign($fileToSign);
    }
}
