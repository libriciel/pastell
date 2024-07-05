<?php

declare(strict_types=1);

use Pastell\Connector\AbstractSedaGeneratorConnector;
use Pastell\Seda\Message\SedaMessageBuilder;
use Pastell\Tests\Connector\AbstractSedaGeneratorConnectorTestCase;

final class SedaGeneratorAsalae10Test extends AbstractSedaGeneratorConnectorTestCase
{
    public function getSedaMessageBuilder(): SedaMessageBuilder
    {
        return new SedaMessageBuilder($this->getTmpFolder());
    }

    public function getSedaConnectorId(): string
    {
        return 'generateur-seda-asalae-1.0';
    }

    public function getExpectedCallDirectory(): string
    {
        return __DIR__ . '/seda-test-cases';
    }

    public function testGenerateArchiveWhenThrowAnException(): void
    {
        $id_ce = $this->createSedaGeneriqueConnector();
        /** @var AbstractSedaGeneratorConnector $sedaGeneriqueConnector */
        $sedaGeneriqueConnector = $this->getConnecteurFactory()->getConnecteurById($id_ce);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            "Impossible de créer le fichier d'archive empty - status : 1"
        );
        $sedaGeneriqueConnector->generateArchive(new \FluxDataTest([]), "empty");
    }
}
