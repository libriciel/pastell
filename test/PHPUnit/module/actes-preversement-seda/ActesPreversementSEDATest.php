<?php

declare(strict_types=1);

class ActesPreversementSEDATest extends PastellTestCase
{
    public const string FLUX_ID = 'actes-preversement-seda';

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     */
    public function testCasNominal(): void
    {
        $result = $this->createDocument(self::FLUX_ID);
        static::assertNotEmpty($result['id_d']);

        $info['id_d'] = $result['id_d'];
        $info['id_e'] = PastellTestCase::ID_E_COL;
        $info['titre'] = "Test d'un versement";

        $this->configureDocument($info['id_d'], $info);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);
        $donneesFormulaire->addFileFromCopy(
            'enveloppe_metier',
            '034-491011698-20171207-CL20171227_06-DE-1-1_0.xml',
            __DIR__ . '/fixtures/acte2-transaction/034-491011698-20171207-CL20171227_06-DE-1-1_0.xml'
        );
        $donneesFormulaire->addFileFromCopy(
            'document',
            '32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf',
            __DIR__ . '/fixtures/acte2-transaction/32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf'
        );
        $donneesFormulaire->addFileFromCopy(
            'document',
            '32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf',
            __DIR__ . '/fixtures/acte2-transaction/32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf',
            1
        );
        $donneesFormulaire->addFileFromCopy(
            'aractes',
            '034-491011698-20171207-CL20171227_06-DE-1-2.xml',
            __DIR__ . '/fixtures/acte2-transaction/034-491011698-20171207-CL20171227_06-DE-1-2.xml'
        );

        $this->postAndTest($info, '32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf');
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     */
    public function testFilenameDifferentThanEnveloppeName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Aucun fichier ayant comme nom « 32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf » n'a été trouvé"
        );

        $document = $this->createDocument(self::FLUX_ID);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->addFileFromCopy(
            'enveloppe_metier',
            '034-491011698-20171207-CL20171227_06-DE-1-1_0.xml',
            __DIR__ . '/fixtures/acte2-transaction/034-491011698-20171207-CL20171227_06-DE-1-1_0.xml'
        );
        $donneesFormulaire->addFileFromCopy(
            'document',
            '99_SE-034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf',
            __DIR__ . '/fixtures/acte2-transaction/32_DP-034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf'
        );
        $donneesFormulaire->addFileFromCopy(
            'aractes',
            '034-491011698-20171207-CL20171227_06-DE-1-2.xml',
            __DIR__ . '/fixtures/acte2-transaction/034-491011698-20171207-CL20171227_06-DE-1-2.xml'
        );

        $this->getInternalAPI()->post(
            sprintf(
                '/entite/%s/document/%s/action/create-acte',
                PastellTestCase::ID_E_COL,
                $document['id_d']
            )
        );
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     */
    private function createOldTransaction(): array
    {
        $result = $this->createDocument(self::FLUX_ID);
        static::assertNotEmpty($result['id_d']);

        $info['id_d'] = $result['id_d'];
        $info['id_e'] = PastellTestCase::ID_E_COL;
        $info['titre'] = "Test d'un versement";

        $this->configureDocument($info['id_d'], $info);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);
        $donneesFormulaire->addFileFromCopy(
            'enveloppe_metier',
            '034-491011698-20171207-CL20171227_06-DE-1-1_0.xml',
            __DIR__ . '/fixtures/acte1-transaction/034-491011698-20171207-CL20171227_06-DE-1-1_0.xml'
        );
        $donneesFormulaire->addFileFromCopy(
            'document',
            '034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf',
            __DIR__ . '/fixtures/acte1-transaction/034-491011698-20171207-CL20171227_06-DE-1-1_1.pdf'
        );
        $donneesFormulaire->addFileFromCopy(
            'document',
            '034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf',
            __DIR__ . '/fixtures/acte1-transaction/034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf',
            1
        );
        $donneesFormulaire->addFileFromCopy(
            'aractes',
            '034-491011698-20171207-CL20171227_06-DE-1-2.xml',
            __DIR__ . '/fixtures/acte1-transaction/034-491011698-20171207-CL20171227_06-DE-1-2.xml'
        );
        return $info;
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     */
    public function testWhitOldTransactionWithoutTdtConnector(): void
    {
        $info = $this->createOldTransaction();
        $this->postAndTest($info, '034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf');
    }

    /**
     * @throws DonneesFormulaireException
     * @throws NotFoundException
     * @throws Exception
     */
    public function testWhitOldTransactionWithTdtConnector(): void
    {
        $id_ce = $this->createConnecteurForTypeDossier('actes-automatique', 'fakeTdt');

        $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce)->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/../actes-generique/fixtures/classification.xml'
        );

        $info = $this->createOldTransaction();
        $this->postAndTest($info, '034-491011698-20171207-CL20171227_06-DE-1-1_2.pdf');
    }

    /**
     * @throws NotFoundException
     */
    private function postAndTest(array $info, string $expectedAnnexe): void
    {
        $result = $this->getInternalAPI()->post("/entite/{$info['id_e']}/document/{$info['id_d']}/action/create-acte");

        preg_match("#Création du document Pastell (.*)#", $result['message'], $matches);
        $id_d = $matches[1];

        $result = $this->getInternalAPI()->get("/entite/{$info['id_e']}/document/$id_d");

        static::assertSame('3.2', $result['data']['classification']);
        static::assertSame('importation', $result['last_action']['action']);

        static::assertSame(
            $expectedAnnexe,
            $this->getDonneesFormulaireFactory()->get($id_d)->getFileName('autre_document_attache')
        );
    }
}
