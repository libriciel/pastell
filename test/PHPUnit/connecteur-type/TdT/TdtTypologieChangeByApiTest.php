<?php

declare(strict_types=1);

class TdtTypologieChangeByApiTest extends PastellTestCase
{
    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testAddTypeActe(): void
    {

        $id_d = $this->createActeGenerique();
        $donneesFormulaire = $this->setActeData($id_d);

        $info = $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_NE', '41_NC', '22_DP']]
        );

        static::assertSame('22_NE', $info['data']['type_acte']);
        static::assertSame('["41_NC","22_DP"]', $info['data']['type_pj']);
        static::assertSame('3 fichier(s) typé(s)', $info['data']['type_piece']);
        static::assertJsonFileEqualsJsonFile(
            __DIR__ . '/fixtures/type_piece_fichier.json',
            $donneesFormulaire->getFilePath('type_piece_fichier')
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testAddWrongTypeActe(): void
    {

        $id_d = $this->createActeGenerique();
        $this->setActeData($id_d);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            'Le type_pj «22_XX» ne correspond pas pour la nature et la classification sélectionnée'
        );
        $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_XX', '41_NC', '22_DP']]
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testAddWrongTypePJ(): void
    {

        $id_d = $this->createActeGenerique();
        $this->setActeData($id_d);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            'Le type_pj «99_XX» ne correspond pas pour la nature et la classification sélectionnée'
        );
        $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_NE','41_NC', '99_XX']]
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testFailCountTypePJ(): void
    {
        $id_d = $this->createActeGenerique();
        $this->setActeData($id_d);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Le nombre de type_pj fourni «2» ne correspond pas au nombre de documents (acte et annexes) «3»');
        $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_NE','41_NC']]
        );
    }


    /**
     * @throws Exception
     */
    private function createActeGenerique(): string
    {
        $connecteur_info = $this->createConnector('fakeTdt', 'Bouchon tdt');

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);

        $connecteurDonneesFormulaire->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/../../module/actes-generique/fixtures/classification.xml'
        );
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'actes-generique', 'TdT');

        $document_info = $this->createDocument('actes-generique');
        return $document_info['id_d'];
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    private function setActeData(string $id_d): DonneesFormulaire
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'acte_nature' => '3',
            'numero_de_lacte' => '1515',
        ]);


        $donneesFormulaire->addFileFromData('arrete', 'arrete.pdf', 'foo');

        $donneesFormulaire->addFileFromData(
            'autre_document_attache',
            'annexe1.pdf',
            'bar',
            0
        );
        $donneesFormulaire->addFileFromData(
            'autre_document_attache',
            'annexe2.pdf',
            'baz',
            1
        );
        return $donneesFormulaire;
    }
}
