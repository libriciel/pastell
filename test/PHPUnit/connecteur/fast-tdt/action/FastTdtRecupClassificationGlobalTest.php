<?php

class FastTdtRecupClassificationGlobalTest extends PastellTestCase
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

        $this->createConnector('fast-tdt', 'Fast TdT');
        $this->createConnector('fast-tdt', 'Fast TdT 2');

        $globalConnector = $this->createConnector('fast-tdt', 'Fast TdT', 0);
        $actionResult = $this->triggerActionOnConnector($globalConnector['id_ce'], 'recup-classification');

        $this->assertTrue($actionResult);
        $expectedMessage = "Résultat :"
            . "<br/>Bourg-en-Bresse(id_ce=14) : Il n'y a pas de nouvelle classification disponible"
            . "<br/>Bourg-en-Bresse(id_ce=15) : Il n'y a pas de nouvelle classification disponible";
        $this->assertLastMessage($expectedMessage);
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

        $globalConnector = $this->createConnector('fast-tdt', 'Fast TdT', 0);
        $actionResult = $this->triggerActionOnConnector($globalConnector['id_ce'], 'recup-classification');

        $this->assertTrue($actionResult);
        $this->assertLastMessage('Résultat :<br/>Bourg-en-Bresse(id_ce=14) : classification récupérée');

        $connectorConfig = $this->getConnecteurFactory()->getConnecteurConfig($connector['id_ce']);
        $this->assertSame('2019-04-18', $connectorConfig->get('classification_date'));
        $this->assertSame(
            mb_convert_encoding(file_get_contents(__DIR__ . '/../fixtures/999-1234----7-2_1.xml'), 'ISO-8859-1'),
            $connectorConfig->getFileContent('classification_file')
        );
    }

    /**
     * When the classification can't be downloaded
     *
     * @test
     */
    public function whenClassificationCannotBeDownloaded()
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
            ->method('get')
            ->willThrowException(new Exception("403 : Forbidden"));
        $this->getObjectInstancier()->setInstance(WebdavWrapper::class, $webdavWrapper);

        $connector = $this->createConnector('fast-tdt', 'Fast TdT');

        $globalConnector = $this->createConnector('fast-tdt', 'Fast TdT', 0);
        $actionResult = $this->triggerActionOnConnector($globalConnector['id_ce'], 'recup-classification');

        $this->assertTrue($actionResult);
        $this->assertLastMessage("Résultat :<br/>Bourg-en-Bresse(id_ce=14) : 403 : Forbidden");
    }
}
