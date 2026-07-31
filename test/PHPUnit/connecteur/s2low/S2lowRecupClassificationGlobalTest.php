<?php

declare(strict_types=1);

class S2lowRecupClassificationGlobalTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    private function getS2low(string $response, int $entityId): array
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => 'ok',
            '/modules/actes/actes_classification_fetch.php?api=1' => $response,
        ]);

        return $this->createConnector('s2low', 'S2LOW', $entityId);
    }

    /**
     * @throws Exception
     */
    public function testWhenGettingLatestClassification(): void
    {
        $this->getS2low(
            file_get_contents(__DIR__ . '/fixtures/999-1234----7-2_1.xml'),
            self::ID_E_COL
        );
        $this->getS2low(
            file_get_contents(__DIR__ . '/fixtures/999-1234----7-2_1.xml'),
            self::ID_E_COL
        );
        $connectorLastInstance = $this->getS2low(
            file_get_contents(__DIR__ . '/fixtures/999-1234----7-2_7.xml'),
            self::ID_E_SERVICE
        );


        $globalConnector = $this->createConnector('s2low', 'S2low', 0);
        $actionResult = $this->triggerActionOnConnector($globalConnector['id_ce'], 'recup-classification');

        static::assertTrue($actionResult);

        $expectedMessage = 'Résultat :'
            . '<br/>Bourg-en-Bresse(id_ce=14) : classification récupérée'
            . '<br/>Bourg-en-Bresse(id_ce=15) : classification récupérée'
            . '<br/>CCAS(id_ce=16) : classification récupérée';

        $this->assertLastMessage($expectedMessage);

        $connectorConfig = $this->getConnecteurFactory()->getConnecteurConfig($connectorLastInstance['id_ce']);

        static::assertSame('2018-11-28', $connectorConfig->get('classification_date'));
        static::assertStringEqualsFile(
            __DIR__ . '/fixtures/999-1234----7-2_7.xml',
            $connectorConfig->getFileContent('classification_file')
        );
    }
}
