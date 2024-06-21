<?php

declare(strict_types=1);

use Pastell\Seda\Message\SedaMessageBuilder;
use Pastell\Seda\Message\VitamSedaMessageBuilder;
use Pastell\Tests\Connector\AbstractSedaGeneratorConnectorTestCase;

final class SedaGeneratorVitam21ConnectorTest extends AbstractSedaGeneratorConnectorTestCase
{
    public function getSedaMessageBuilder(): SedaMessageBuilder
    {
        return new VitamSedaMessageBuilder(
            $this->getTmpFolder(),
            $this->getPastellMetadataService()
        );
    }

    public function getSedaConnectorId(): string
    {
        return 'generateur-seda-vitam-2.1';
    }

    public function getExpectedCallDirectory(): string
    {
        return __DIR__ . '/seda-test-cases';
    }
}
