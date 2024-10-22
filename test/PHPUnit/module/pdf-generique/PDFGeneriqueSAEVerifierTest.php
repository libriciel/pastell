<?php

declare(strict_types=1);

class PDFGeneriqueSAEVerifierTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws Exception
     */
    public function testCasNominal(): void
    {
        $this->mockCurl([
            'https://sae/sedaMessages/sequence:ArchiveTransfer/message:Acknowledgement/' .
            'originOrganizationIdentification:Pastell_API/' .
            'originMessageIdentifier:15ef78ef665a8777c33d1125783707f8dfb190f82869dc9248e46c5ed396d70b_1542893421'
            => file_get_contents(__DIR__ . '/fixtures/acuse-de-reception-asalae.xml'),
        ]);

        $connectorId = $this->createConnector('as@lae-rest', 'Asalae')['id_ce'];
        $this->associateFluxWithConnector($connectorId, 'pdf-generique', 'SAE');
        $this->configureConnector($connectorId, [
            'url' => 'https://sae',
            'login' => 'login',
            'password' => 'password',
        ]);

        $documentId = $this->createDocument('pdf-generique')['id_d'];

        $this->getInternalAPI()->patch(
            "/entite/1/document/$documentId",
            [
                'libelle' => 'Test pdf générique',
                'envoi_sae' => '1',
                'sae_transfert_id' => '15ef78ef665a8777c33d1125783707f8dfb190f82869dc9248e46c5ed396d70b_1542893421',
            ]
        );
        $this->getDonneesFormulaireFactory()->get($documentId)->addFileFromCopy(
            'sae_bordereau',
            'bordereau.xml',
            __DIR__ . '/fixtures/bordereau.xml'
        );

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeOnDocument(1, 0, $documentId, 'verif-sae');

        $this->assertEquals(
            "Récupération de l'accusé de réception : Acknowledgement - Votre transfert d'archive a été pris en compte par la plate-forme as@lae",
            $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage()
        );

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($documentId);
        $this->assertFileEquals(
            __DIR__ . '/fixtures/acuse-de-reception-asalae.xml',
            $donneesFormulaire->getFilePath('ar_sae')
        );

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
        $this->assertEquals('ar-recu-sae', $documentActionEntite->getLastAction(1, $documentId));
    }


    /**
     * @throws Exception
     */
    public function testCasIdTransfertNotAvailable(): void
    {
        $connectorId = $this->createConnector('as@lae-rest', 'Asalae')['id_ce'];
        $this->associateFluxWithConnector($connectorId, 'pdf-generique', 'SAE');
        $this->configureConnector($connectorId, [
            'url' => 'https://sae',
            'login' => 'login',
            'password' => 'password',
        ]);

        $documentId = $this->createDocument('pdf-generique')['id_d'];

        $this->getInternalAPI()->patch(
            "/entite/1/document/$documentId",
            [
                'libelle' => 'Test pdf générique',
                'envoi_sae' => '1',
            ]
        );

        $this->getDonneesFormulaireFactory()->get($documentId)->addFileFromCopy(
            'sae_bordereau',
            'bordereau.xml',
            __DIR__ . '/fixtures/bordereau.xml'
        );

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeOnDocument(1, 0, $documentId, 'verif-sae');

        $this->assertEquals(
            "L'identifiant du transfert n'a pas été trouvé",
            $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage()
        );

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
        $this->assertEquals('verif-sae-erreur', $documentActionEntite->getLastAction(1, $documentId));
    }


    /**
     * @throws Exception
     */
    public function testCasNonDisponible(): void
    {
        $this->mockCurl(
            [
                'https://sae/sedaMessages/sequence:ArchiveTransfer/message:Acknowledgement/' .
                'originOrganizationIdentification:Pastell_API/originMessageIdentifier:mon_id_de_transfert_phpunit'
                => 'pas disponible erreur 500',
            ],
            500
        );

        $connectorId = $this->createConnector('as@lae-rest', 'Asalae')['id_ce'];
        $this->associateFluxWithConnector($connectorId, 'pdf-generique', 'SAE');
        $this->configureConnector($connectorId, [
            'url' => 'https://sae',
            'login' => 'login',
            'password' => 'password',
        ]);

        $documentId = $this->createDocument('pdf-generique')['id_d'];

        $this->getInternalAPI()->patch(
            "/entite/1/document/$documentId",
            [
                'libelle' => 'Test pdf générique',
                'envoi_sae' => '1',
                'sae_transfert_id' => 'mon_id_de_transfert_phpunit',
            ]
        );

        $this->getDonneesFormulaireFactory()->get($documentId)->addFileFromCopy(
            'sae_bordereau',
            'bordereau.xml',
            __DIR__ . '/fixtures/bordereau.xml'
        );

        $actionExecutorFactory = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class);
        $actionExecutorFactory->executeOnDocument(1, 0, $documentId, 'verif-sae');

        $this->assertEquals(
            "pas disponible erreur 500 - code d'erreur HTTP : 500",
            $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage()
        );

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
        $this->assertEquals('modification', $documentActionEntite->getLastAction(1, $documentId));
    }
}
