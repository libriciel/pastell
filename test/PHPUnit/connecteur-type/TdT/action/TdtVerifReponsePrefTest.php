<?php

class TdtVerifReponsePrefTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @param array $documentData
     * @return mixed
     */
    private function createConnectorAndDocument(array $documentData = [])
    {
        $connector = $this->createConnector('s2low', 's2low');
        $this->configureConnector($connector['id_ce'], [
            'url' => '',
            'authentication_for_teletransmisson' => true
        ]);
        $this->associateFluxWithConnector($connector['id_ce'], 'actes-reponse-prefecture', 'TdT');

        $document = $this->createDocument('actes-reponse-prefecture');
        $id_d = $document['id_d'];
        $this->configureDocument($id_d, [
                'acte_nature' => 1,
                'numero_de_lacte' => '123456789',
                'related_transaction_id' => 1,
                'transaction_id' => 2,
            ] + $documentData);

        return $id_d;
    }

    public function testWithCourrierSimple()
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_get_status.php?transaction=3' => "OK\n4\nUNUSED"
        ]);

        $id_d = $this->createConnectorAndDocument([
            'type_reponse' => 2
        ]);

        $this->triggerActionOnDocument($id_d, 'verif-reponse-tdt');
        $this->assertLastMessage('Ce type de réponse de la préfécture ne prévoit pas d\'acquittement');
    }

    /**
     * @throws NotFoundException
     */
    public function testWithPieceComplementaire()
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_get_status.php?transaction=3' =>
                "OK\n4\n" . file_get_contents(__DIR__ . '/../fixtures/ARPieceComplementaire.xml')
        ]);

        $id_d = $this->createConnectorAndDocument([
            'type_reponse' => 3,
            'response_transaction_id' => 3
        ]);

        $this->triggerActionOnDocument($id_d, 'verif-reponse-tdt');
        $this->assertLastMessage("Réception d'un message (demande_piece_complementaire) de la préfecture");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $this->assertSame('1', $donneesFormulaire->get('has_acquittement'));
        $this->assertStringEqualsFile(
            __DIR__ . '/../fixtures/ARPieceComplementaire.xml',
            $donneesFormulaire->getFileContent('acquittement_file')
        );
    }

    public function testErrorWithLettreObservation(): void
    {
        $this->mockCurl([
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_get_status.php?transaction=3' =>
                mb_convert_encoding("OK\n-1\nErreur: Détail erreur", 'ISO-8859-1')
        ]);

        $id_d = $this->createConnectorAndDocument([
            'type_reponse' => 4,
            'response_transaction_id' => 3
        ]);

        $this->triggerActionOnDocument($id_d, 'verif-reponse-tdt');
        $this->assertLastMessage('Transaction en erreur sur le TdT : Erreur: Détail erreur');
    }
}
