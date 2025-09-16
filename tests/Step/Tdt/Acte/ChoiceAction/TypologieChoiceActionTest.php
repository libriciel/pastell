<?php

declare(strict_types=1);

namespace Pastell\Tests\Step\Tdt\Acte\ChoiceAction;

use DonneesFormulaire;
use Exception;
use NotFoundException;
use PastellTestCase;
use UnrecoverableException;
use TypeDossierLoader;

final class TypologieChoiceActionTest extends PastellTestCase
{
    public const TDT_ACTES_ONLY = 'tdt-actes-only';
    private TypeDossierLoader $typeDossierLoader;

    /**
     * @throws \TypeDossierException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->typeDossierLoader = $this->getObjectInstancier()->getInstance(TypeDossierLoader::class);
        $this->typeDossierLoader->createTypeDossierDefinitionFile(self::TDT_ACTES_ONLY);
    }

    protected function tearDown(): void
    {
        $this->typeDossierLoader->unload();
        parent::tearDown();
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testAddTypeActe(): void
    {

        $id_d = $this->createActe();
        $donneesFormulaire = $this->setActeData($id_d);

        $info = $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_NE', '41_NC', '22_DP']]
        );

        self::assertSame('22_NE', $info['data']['type_acte']);
        self::assertSame('["41_NC","22_DP"]', $info['data']['type_pj']);
        self::assertSame('3 fichier(s) typé(s)', $info['data']['type_piece']);
        self::assertJsonFileEqualsJsonFile(
            __DIR__ . '/../fixtures/type_piece_fichier.json',
            $donneesFormulaire->getFilePath('type_piece_fichier')
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testAddWrongTypeActe(): void
    {

        $id_d = $this->createActe();
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

        $id_d = $this->createActe();
        $this->setActeData($id_d);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            'Le type_pj «99_XX» ne correspond pas pour la nature et la classification sélectionnée'
        );
        $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_NE', '41_NC', '99_XX']]
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testFailCountTypePJ(): void
    {
        $id_d = $this->createActe();
        $this->setActeData($id_d);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            'Le nombre de type_pj fourni «2» ne correspond pas au nombre de documents (acte et annexes) «3»'
        );
        $this->getInternalAPI()->patch(
            "/Entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['22_NE', '41_NC']]
        );
    }

    /**
     * @throws Exception
     */
    private function createActe(): string
    {
        $connecteur_info = $this->createConnector('fakeTdt', 'Bouchon tdt');

        $connecteurDonneesFormulaire = $this->getDonneesFormulaireFactory()
            ->getConnecteurEntiteFormulaire($connecteur_info['id_ce']);

        $connecteurDonneesFormulaire->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/../fixtures/classification.xml'
        );
        $this->associateFluxWithConnector($connecteur_info['id_ce'], self::TDT_ACTES_ONLY, 'TdT');

        $document_info = $this->createDocument(self::TDT_ACTES_ONLY);
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


        $donneesFormulaire->addFileFromData('actes', 'actes.pdf', 'foo');

        $donneesFormulaire->addFileFromData(
            'annexe',
            'annexe1.pdf',
            'bar',
            0
        );
        $donneesFormulaire->addFileFromData(
            'annexe',
            'annexe2.pdf',
            'baz',
            1
        );
        return $donneesFormulaire;
    }
}
