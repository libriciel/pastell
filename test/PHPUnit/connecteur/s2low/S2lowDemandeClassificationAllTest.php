<?php

declare(strict_types=1);

class S2lowDemandeClassificationAllTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    private function setUpS2lowConnector(string $curl_response, int $id_e): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => 'ok',
            '/modules/actes/actes_classification_request.php?api=1' => '',
            '/modules/actes/actes_classification_fetch.php?api=1' => $curl_response,
        ]);

        $this->createConnector('s2low', 'S2LOW', $id_e);
    }

    public function testWhenGettingLatestClassification(): void
    {
        $this->setUpS2lowConnector('S²low a répondu : OK', self::ID_E_COL);
        $this->setUpS2lowConnector('S²low a répondu : OK', self::ID_E_SERVICE);

        $globalConnector = $this->createConnector('s2low', 'S2low', 0);
        $actionResult = $this->triggerActionOnConnector($globalConnector['id_ce'], 'demande-classification');
        static::assertTrue($actionResult);

        $expectedMessage = 'Résultat :'
            . '<br/>Bourg-en-Bresse(id_ce=14) : demande de classification envoyée'
            . '<br/>CCAS(id_ce=15) : demande de classification envoyée';

        $this->assertLastMessage($expectedMessage);
    }
}
