<?php

namespace Pastell\Tests\Service\Connecteur;

use Exception;
use FluxEntiteSQL;
use Pastell\Service\Connecteur\ConnecteurActionService;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurCreationService;
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
    public function testAddConnecteurWithSameType()
    {
        $id_fe = $this->getConnecteurAssociationService()->addConnecteurAssociation(1, 1, "signature", 0, "actes-generique");
        $this->assertEquals(1, $this->getFluxEntiteSQL()->getConnecteurById($id_fe)['id_ce']);

        $connecteur_action_message = $this->getConnecteurActionService()->getByIdCe(1)[0]['message'];
        $this->assertEquals("Association au type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1", $connecteur_action_message);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testAddConnecteurWithSameTypeWithoutNumSameType()
    {
        $id_fe_1 = $this->getConnecteurAssociationService()->addConnecteurAssociation(1, 1, "signature", 0, "actes-generique");

        $id_ce_2 = $this->createConnector('iParapheur', "Connecteur i-Parapheur")['id_ce'];
        $connecteur_2_action_message = $this->getConnecteurActionService()->getByIdCe($id_ce_2)[0]['message'];
        $this->assertEquals("Le connecteur iParapheur « Connecteur i-Parapheur » a été créé", $connecteur_2_action_message);

        $this->getConnecteurAssociationService()->addConnecteurAssociation(1, $id_ce_2, "signature", 0, "actes-generique");

        $this->assertFalse($this->getFluxEntiteSQL()->getConnecteurById($id_fe_1));

        $connecteur_1_action_message = $this->getConnecteurActionService()->getByIdCe(1)[0]['message'];
        $this->assertEquals("Dissociation du type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1", $connecteur_1_action_message);
        $connecteur_2_action_message = $this->getConnecteurActionService()->getByIdCe($id_ce_2)[0]['message'];
        $this->assertEquals("Association au type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1", $connecteur_2_action_message);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetConnecteurWithSameType()
    {
        $id_fe_1 = $this->getConnecteurAssociationService()->addConnecteurAssociation(1, 1, "signature", 0, "actes-generique");

        $id_ce_2 = $this->createConnector('iParapheur', "Connecteur i-Parapheur")['id_ce'];
        $id_fe_2 = $this->getConnecteurAssociationService()->addConnecteurAssociation(1, $id_ce_2, "signature", 0, "actes-generique", 1);
        $connecteur_2_action_message = $this->getConnecteurActionService()->getByIdCe($id_ce_2)[0]['message'];
        $this->assertEquals("Association au type de dossier actes-generique en position 2 du type de connecteur signature pour l'entité id_e = 1", $connecteur_2_action_message);

        $this->assertEquals(
            $id_fe_1,
            $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', "signature", 0)['id_fe']
        );
        $this->assertEquals(
            $id_fe_2,
            $this->getFluxEntiteSQL()->getConnecteur(1, 'actes-generique', "signature", 1)['id_fe']
        );
        $this->assertEquals(
            1,
            $this->getFluxEntiteSQL()->getConnecteurId(1, 'actes-generique', "signature", 0)
        );
        $this->assertEquals(
            $id_ce_2,
            $this->getFluxEntiteSQL()->getConnecteurId(1, 'actes-generique', "signature", 1)
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testDeleteConnecteurAssociationById_fe()
    {
        $id_fe = $this->getConnecteurAssociationService()->addConnecteurAssociation(1, 1, "signature", 0, "actes-generique");
        $connecteur_action_message = $this->getConnecteurActionService()->getByIdCe(1)[0]['message'];
        $this->assertEquals("Association au type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1", $connecteur_action_message);

        $this->getConnecteurAssociationService()->deleteConnecteurAssociationById_fe($id_fe);
        $connecteur_action_message = $this->getConnecteurActionService()->getByIdCe(1)[0]['message'];
        $this->assertEquals("Dissociation du type de dossier actes-generique en position 1 du type de connecteur signature pour l'entité id_e = 1", $connecteur_action_message);
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
}
