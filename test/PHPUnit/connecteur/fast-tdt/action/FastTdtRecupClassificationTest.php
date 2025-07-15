<?php

class FastTdtRecupClassificationTest extends PastellTestCase
{
    /**
     * When no new classification is available
     *
     * @test
     */
    public function whenThereIsNoNewClassification()
    {
        $webdavWrapper = $this->createMock(WebdavWrapper::class);
        $webdavWrapper->method('listFolder')->willReturn([]);
        $this->getObjectInstancier()->setInstance(WebdavWrapper::class, $webdavWrapper);

        $connector = $this->createConnector('fast-tdt', 'Fast TdT');
        $this->configureConnector($connector['id_ce'], [
            'departement' => 999,
            'numero_abonnement' => 1234
        ]);
        $actionResult = $this->triggerActionOnConnector($connector['id_ce'], 'recup-classification');

        $this->assertTrue($actionResult);
        $this->assertLastMessage("Il n'y a actuellement pas de nouvelle classification disponible");
    }


    /**
     * When getting latest available classification file
     *
     * @test
     */
    public function whenGettingLatestClassification()
    {
        $webdavWrapper = $this->createMock(WebdavWrapper::class);
        $webdavWrapper
            ->method('listFolder')
            ->willReturn([
                '999-1234----7-2_1.xml',
                '999-1234----7-2_7.xml',
                '999-1234----7-2_9.xml',
            ]);
        $webdavWrapper
            ->expects($this->exactly(3))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                file_get_contents(__DIR__ . '/../fixtures/999-1234----7-2_1.xml'),
                file_get_contents(__DIR__ . '/../fixtures/999-1234----7-2_7.xml'),
                file_get_contents(__DIR__ . '/../fixtures/999-1234----7-2_9.xml')
            );
        $webdavWrapper
            ->expects($this->exactly(2))
            ->method('delete')
            ->willReturn(['statusCode' => 204]);

        $this->getObjectInstancier()->setInstance(WebdavWrapper::class, $webdavWrapper);

        $connector = $this->createConnector('fast-tdt', 'Fast TdT');
        $this->configureConnector($connector['id_ce'], [
            'departement' => 999,
            'numero_abonnement' => 1234
        ]);
        $actionResult = $this->triggerActionOnConnector($connector['id_ce'], 'recup-classification');

        $this->assertTrue($actionResult);
        $this->assertLastMessage("La classification a été mise à jour");

        $connectorConfig = $this->getConnecteurFactory()->getConnecteurConfig($connector['id_ce']);
        $this->assertSame('2019-04-18', $connectorConfig->get('classification_date'));
        $this->assertSame(
            mb_convert_encoding(file_get_contents(__DIR__ . '/../fixtures/999-1234----7-2_1.xml'), 'ISO-8859-1'),
            $connectorConfig->getFileContent('classification_file')
        );
    }

    /**
     * When getting classification with a connector configures for PES
     *
     * @test
     */
    public function whenTheConnectorIsNotConfiguredForActes()
    {
        $connector = $this->createConnector('fast-tdt', 'Fast TdT');
        $this->configureConnector($connector['id_ce'], [
            'url' => 'https://demo-parapheur.dfast.fr/parapheur-soap/soap/v1/Documents?wsdl',
            'departement' => 999,
            'numero_abonnement' => 1234
        ]);
        $actionResult = $this->triggerActionOnConnector($connector['id_ce'], 'recup-classification');

        $this->assertFalse($actionResult);
        $this->assertLastMessage("La classification n'est récupérable qu'avec le connecteur configuré pour les actes");
    }
}
