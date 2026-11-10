<?php

class FastTdtTestConnectionTest extends PastellTestCase
{
    /**
     * When the connection is successful
     */
    public function testWhenTheConnectionIsOk()
    {
        $webdavWrapper = $this->createMock(WebdavWrapper::class);
        $webdavWrapper->method('isConnected')->willReturn(true);
        $this->getObjectInstancier()->setInstance(WebdavWrapper::class, $webdavWrapper);

        $connector = $this->createConnector('fast-tdt', 'Fast TdT');
        $actionResult = $this->triggerActionOnConnector($connector['id_ce'], 'test-connection');

        $this->assertTrue($actionResult);
        $this->assertLastMessage("La connexion est réussie");
    }

    /**
     * When the connection is not successful
     */
    public function testWhenTheConnectionIsNotOk()
    {
        $webdavWrapper = $this->createMock(WebdavWrapper::class);
        $webdavWrapper
            ->method('isConnected')
            ->willThrowException(new Exception("Le serveur ne présente pas le header Dav"));

        $this->getObjectInstancier()->setInstance(WebdavWrapper::class, $webdavWrapper);

        $connector = $this->createConnector('fast-tdt', 'Fast TdT');
        $actionResult = $this->triggerActionOnConnector($connector['id_ce'], 'test-connection');

        $this->assertFalse($actionResult);
        $this->assertLastMessage("Le serveur ne présente pas le header Dav");
    }
}
