<?php

namespace Pastell\Tests\Service\Connecteur;

use Exception;
use FluxEntiteSQL;
use Pastell\Service\Connecteur\ConnecteurActionService;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use PastellTestCase;
use UnrecoverableException;

class ConnecteurAssociationServiceTest extends PastellTestCase
{
    private function getConnecteurAssociationService(): ConnecteurAssociationService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurAssociationService::class);
    }

    private function getFluxEntiteSQL(): FluxEntiteSQL
    {
        return $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);
    }

    private function getConnecteurActionService(): ConnecteurActionService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurActionService::class);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAddConnecteurWithSameType(): void
    {
        $id_fe = $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, 1, 'signature', 0, 'actes-generique');
        static::assertSame(1, $this->getFluxEntiteSQL()->getConnecteurById($id_fe)['id_ce']);

        static::assertSame(
            "Association au type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe(1)[0]['message']
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAddConnecteurWithSameTypeWithoutNumSameType(): void
    {
        $id_fe_1 = $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, 1, 'signature', 0, 'actes-generique');

        $id_ce_2 = $this->createConnector('iParapheur', 'Connecteur i-Parapheur')['id_ce'];
        static::assertSame(
            'Le connecteur iParapheur « Connecteur i-Parapheur » a été créé',
            $this->getConnecteurActionService()->getByIdCe($id_ce_2)[0]['message']
        );

        $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, $id_ce_2, 'signature', 0, 'actes-generique');

        static::assertFalse($this->getFluxEntiteSQL()->getConnecteurById($id_fe_1));
        static::assertSame(
            "Dissociation du type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe(1)[0]['message']
        );
        static::assertSame(
            "Association au type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe($id_ce_2)[0]['message']
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetConnecteurWithSameType(): void
    {
        $id_fe_1 = $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, 1, 'signature', 0, 'actes-generique');

        $id_ce_2 = $this->createConnector('iParapheur', 'Connecteur i-Parapheur')['id_ce'];
        $id_fe_2 = $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, $id_ce_2, 'signature', 0, 'actes-generique', 1);
        static::assertSame(
            "Association au type de dossier actes-generique en position 2 du type de connecteur signature pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe($id_ce_2)[0]['message']
        );

        static::assertSame(
            $id_fe_1,
            $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', 'signature', 0)['id_fe']
        );
        static::assertSame(
            $id_fe_2,
            $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', 'signature', 1)['id_fe']
        );
        static::assertSame(
            1,
            $this->getFluxEntiteSQL()->getConnecteurId(1, 'actes-generique', 'signature', 0)
        );
        static::assertEquals(
            $id_ce_2,
            $this->getFluxEntiteSQL()->getConnecteurId(1, 'actes-generique', 'signature', 1)
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testDeleteConnecteurAssociationById_fe(): void
    {
        $id_fe = $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, 1, 'signature', 0, 'actes-generique');
        static::assertSame(
            "Association au type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe(1)[0]['message']
        );

        $this->getConnecteurAssociationService()->deleteConnecteurAssociationById_fe($id_fe);
        static::assertSame(
            "Dissociation du type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe(1)[0]['message']
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testHeritageConnecteurEntiteRacine(): void
    {

        $id_ce = $this->createConnector(
            'transformation-generique',
            'Connecteur transformation-generique entité racine',
            0,
            0
        )['id_ce'];
        $connecteurConfig = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($id_ce);
        $connecteurConfig->addFileFromData(
            'definition',
            'definition.json',
            '{"foo": "bar"}',
        );

        $id_fe = $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, $id_ce, 'transformation', 0, 'ls-document-pdf');

        static::assertSame(
            "Association au type de dossier ls-document-pdf en position 1 du type de connecteur transformation pour l'entité id_e = 1",
            $this->getConnecteurActionService()->getByIdCe($id_ce)[0]['message']
        );
        static::assertSame(
            $id_fe,
            $this->getFluxEntiteSQL()->getConnecteur(1, 'ls-document-pdf', 'transformation', 0)['id_fe']
        );
        static::assertEquals(
            $id_ce,
            $this->getFluxEntiteSQL()->getConnecteurId(1, 'ls-document-pdf', 'transformation', 0)
        );

        $id_d = $this->createDocument('ls-document-pdf')['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donneesFormulaire->setTabData(['libelle' => 'LIBELLE']);
        $donneesFormulaire->addFileFromCopy(
            'document',
            'vide.pdf',
            __DIR__ . "./../fixtures/vide.pdf"
        );

        $this->triggerActionOnDocument($id_d, 'orientation');
        $this->assertLastMessage("sélection automatique de l'action suivante");

        $this->triggerActionOnDocument($id_d, 'transformation');
        $this->assertLastMessage('Transformation terminée');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        static::assertSame('bar', $donneesFormulaire->get('foo'));
    }

    /**
     * @throws UnrecoverableException
     */
    public function testNoTypeDossierAssociation(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le type de dossier est manquant.");
        $this->getConnecteurAssociationService()->addConnecteurAssociation(1, 1);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testMigrateConnecteurAssociation(): void
    {
        $id_ce_source = $this->createConnector('iParapheur', 'Connecteur source')['id_ce'];
        $this->getConnecteurAssociationService()
            ->addConnecteurAssociation(1, $id_ce_source, 'signature', 0, 'ls-document-pdf');

        $id_ce_target = $this->createConnector('iparapheur-rest', 'Connecteur cible')['id_ce'];

        $count = $this->getConnecteurAssociationService()
            ->migrateConnecteurAssociation($id_ce_source, $id_ce_target);

        static::assertSame(1, $count);
        static::assertSame(
            (int)$id_ce_target,
            $this->getFluxEntiteSQL()->getConnecteurId(1, 'ls-document-pdf', 'signature', 0)
        );
    }

    public function testMigrateConnecteurAssociationWithDifferentTypes(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Les connecteurs ne sont pas du même type : signature (source) vs GED (cible)');

        $id_ce_source = $this->createConnector('iParapheur', 'Connecteur source')['id_ce'];
        $id_ce_target = $this->createConnector('depot-sftp', 'Connecteur cible')['id_ce'];

        $this->getConnecteurAssociationService()
            ->migrateConnecteurAssociation($id_ce_source, $id_ce_target);
    }
}
