<?php

use Pastell\Service\ChorusPro\ChorusProImportCreationService;
use Pastell\Service\ChorusPro\ChorusProImportUtilService;
use Pastell\Service\Document\DocumentEntiteService;

class CPPImporterFactureTest extends ExtensionCppTestCase
{
    private const DATE_MIN_STATUT_COURANT_OLD = "2018-01-01";
    private const DATE_MIN_STATUT_COURANT_YOUNG = "2020-01-01";

    /**
     * When the connector is associated with the facture-cpp flux
     *
     * @test
     * @throws Exception
     */
    public function whenConnectorAssociatedWithFactureCpp()
    {
        $result = $this->createConnector('cpp', 'CPP');
        $id_ce = $result['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'facture-cpp', 'PortailFacture');

        $importerFacture = new CPPImporterFacture($this->getObjectInstancier());
        $importerFacture->setConnecteurId('PortailFacture', $id_ce);
        $chorusProImportCreationService = $this->getObjectInstancier()->getInstance(
            ChorusProImportCreationService::class
        );
        $chorusProImportCreationService->setChorusProConfigService(self::ID_E_COL, self::ID_U_ADMIN, $id_ce);
        $this->assertEquals('facture-cpp', $chorusProImportCreationService->getFluxName());
    }

    /**
     * When the connector is associated with the facture-cpp and statut-facture-cpp flux
     *
     * @test
     * @throws Exception
     */
    public function whenConnectorAssociatedWithTwoFlux()
    {
        $result = $this->createConnector('cpp', 'CPP');
        $id_ce = $result['id_ce'];

        $this->associateFluxWithConnector($id_ce, 'facture-cpp', 'PortailFacture');
        $this->associateFluxWithConnector($id_ce, 'statut-facture-cpp', 'PortailFacture');

        $importerFacture = new CPPImporterFacture($this->getObjectInstancier());
        $importerFacture->setConnecteurId('PortailFacture', $id_ce);
        $chorusProImportCreationService = $this->getObjectInstancier()->getInstance(
            ChorusProImportCreationService::class
        );
        $chorusProImportCreationService->setChorusProConfigService(self::ID_E_COL, self::ID_U_ADMIN, $id_ce);
        $this->assertEquals('facture-cpp', $chorusProImportCreationService->getFluxName());
    }

    /**
     * When the connector is associated with the statut-facture-cpp flux
     *
     * @test
     * @throws Exception
     */
    public function whenConnectorAssociatedWithStatutFactureCpp()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Le connecteur n'est associé à aucun type de dossier pour la récupération des factures..."
        );
        $result = $this->createConnector('cpp', 'CPP');
        $id_ce = $result['id_ce'];
        $this->associateFluxWithConnector($id_ce, 'statut-facture-cpp', 'PortailFacture');

        $importerFacture = new CPPImporterFacture($this->getObjectInstancier());
        $importerFacture->setConnecteurId('PortailFacture', $id_ce);
        $chorusProImportCreationService = $this->getObjectInstancier()->getInstance(
            ChorusProImportCreationService::class
        );
        $chorusProImportCreationService->setChorusProConfigService(self::ID_E_COL, self::ID_U_ADMIN, $id_ce);
        $this->assertEquals('facture-cpp', $chorusProImportCreationService->getFluxName());
    }

    /**
     * @throws Exception
     */
    public function testCreerFactureSansConnecteurParametrage()
    {
        $this->mockCPPWrapper(
            self::DATE_MIN_STATUT_COURANT_OLD,
            self::DATE_MIN_STATUT_COURANT_OLD
        );
        $id_ce = $this->createCppConnector("facture-cpp");

        $this->triggerActionOnConnector($id_ce, 'import-facture');

        /** @var CPP $cpp */
        $cpp = $this->getConnecteurFactory()->getConnecteurById($id_ce);

        $last_message_expected = 'Récupération des factures ayant changé de statut entre le ' .
            $cpp->getDateDepuisLe() . ' et le ' . $cpp->getDateJusquAu() .
            ' et synchronisation des factures déja présentes:<br/>';
        $last_message_expected .= '"Création du document" pour 2 facture(s): 4100169, 3346947, <br/>';
        $this->assertLastMessage($last_message_expected);
    }

    /**
     * @throws Exception
     */
    public function testImportFactureNominalRecu()
    {
        $this->setMinDateStatutCourant(
            'recu',
            self::DATE_MIN_STATUT_COURANT_OLD,
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_VALEUR
        );
        $this->setMinDateStatutCourant(
            'travaux',
            self::DATE_MIN_STATUT_COURANT_YOUNG,
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_VALEUR
        );
        $this->mockCPPWrapper(
            self::DATE_MIN_STATUT_COURANT_OLD,
            self::DATE_MIN_STATUT_COURANT_YOUNG
        );
        $id_ce = $this->prepareFluxFactureCpp();
        $this->triggerActionOnConnector($id_ce, 'import-facture');

        $documentEntiteService = $this->getObjectInstancier()->getInstance(DocumentEntiteService::class);

        $list_facture = $documentEntiteService->getDocumentsByType(1, 'facture-cpp');
        $this->assertEquals(1, count($list_facture));
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($list_facture[0]['id_d']);

        $type_integration_libelle = $donneesFormulaire->getFormulaire()->getField('type_integration')->getSelect();
        $this->assertEquals(
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_VALEUR,
            $type_integration_libelle[$donneesFormulaire->get('type_integration')]
        );

        $this->assertFileEquals(
            self::FICHIER_PIVOT,
            $donneesFormulaire->getFilePath('fichier_facture')
        );
    }

    /**
     * @throws Exception
     */
    public function testImportFactureNominalTravaux()
    {
        $this->setMinDateStatutCourant(
            'recu',
            self::DATE_MIN_STATUT_COURANT_YOUNG,
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_VALEUR
        );
        $this->setMinDateStatutCourant(
            'travaux',
            self::DATE_MIN_STATUT_COURANT_OLD,
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_VALEUR
        );
        $this->mockCPPWrapper(
            self::DATE_MIN_STATUT_COURANT_YOUNG,
            self::DATE_MIN_STATUT_COURANT_OLD
        );
        $id_ce = $this->prepareFluxFactureCpp();
        $this->triggerActionOnConnector($id_ce, 'import-facture');

        $documentEntiteService = $this->getObjectInstancier()->getInstance(DocumentEntiteService::class);

        $list_facture = $documentEntiteService->getDocumentsByType(1, 'facture-cpp');
        $this->assertEquals(1, count($list_facture));
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($list_facture[0]['id_d']);

        $type_integration_libelle = $donneesFormulaire->getFormulaire()->getField('type_integration')->getSelect();
        $this->assertEquals(
            ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_VALEUR,
            $type_integration_libelle[$donneesFormulaire->get('type_integration')]
        );

        $this->assertFileEquals(
            self::FICHIER_PIVOT,
            $donneesFormulaire->getFilePath('fichier_facture')
        );
    }

    /**
     * @throws Exception
     */
    public function testNoImportFactureWhenMinDateDepotIsTooYoung()
    {
        $this->mockCPPWrapper(
            self::DATE_MIN_STATUT_COURANT_YOUNG,
            self::DATE_MIN_STATUT_COURANT_YOUNG
        );
        $id_ce = $this->prepareFluxFactureCpp();
        $this->triggerActionOnConnector($id_ce, 'import-facture');

        $documentEntiteService = $this->getObjectInstancier()->getInstance(DocumentEntiteService::class);

        $list_facture = $documentEntiteService->getDocumentsByType(1, 'facture-cpp');
        $this->assertEmpty(count($list_facture));
    }

    /**
     * @throws Exception
     */
    public function testImportFactureWhenMinDateDepotIsOld()
    {
        $this->mockCPPWrapper(
            self::DATE_MIN_STATUT_COURANT_OLD,
            self::DATE_MIN_STATUT_COURANT_OLD
        );
        $id_ce = $this->prepareFluxFactureCpp();
        $this->triggerActionOnConnector($id_ce, 'import-facture');

        $documentEntiteService = $this->getObjectInstancier()->getInstance(DocumentEntiteService::class);

        $list_facture = $documentEntiteService->getDocumentsByType(1, 'facture-cpp');
        $this->assertEquals(2, count($list_facture));
    }

    /**
     * @throws Exception
     */
    public function testNoCreateFactureRecuWhenStatutFinal()
    {
        $this->mockCPPWrapper(
            self::DATE_MIN_STATUT_COURANT_OLD,
            self::DATE_MIN_STATUT_COURANT_OLD,
            'MISE_EN_PAIEMENT'
        );
        $id_ce = $this->createCppConnector("facture-cpp");

        $this->triggerActionOnConnector($id_ce, 'import-facture');

        /** @var CPP $cpp */
        $cpp = $this->getConnecteurFactory()->getConnecteurById($id_ce);

        $last_message_expected =
            'Récupération des factures ayant changé de statut entre le ' .
            $cpp->getDateDepuisLe() . ' et le ' . $cpp->getDateJusquAu() .
            ' et synchronisation des factures déja présentes:<br/>';
        $last_message_expected .= '"Création du document" pour 1 facture(s): 4100169, <br/>';
        $this->assertLastMessage($last_message_expected);
    }

    /**
     * @param $id_d
     * @param $date_statut_courant
     * @param $type_integration
     * @throws Exception
     */
    private function setMinDateStatutCourant($id_d, $date_statut_courant, $type_integration)
    {
        $sql = "INSERT INTO document_index(id_d, field_name, field_value) VALUES (?,?,?)";
        $this->getSQLQuery()->query($sql, $id_d, 'date_statut_courant', $date_statut_courant);

        $sql = "INSERT INTO document_index(id_d, field_name, field_value) VALUES (?,?,?)";
        $this->getSQLQuery()->query($sql, $id_d, 'type_integration', $type_integration);

        $sql = "INSERT INTO document_entite(id_d, id_e) VALUES (?,?)";
        $this->getSQLQuery()->query($sql, $id_d, 1);
    }

    /**
     * @return mixed
     */
    private function prepareFluxFactureCpp()
    {
        $id_ce = $this->createConnector('parametrage-flux-facture-cpp', "Paramétrage test")['id_ce'];
        $this->associateFluxWithConnector($id_ce, "facture-cpp", "ParametrageFlux");

        $id_ce = $this->createCppConnector("facture-cpp");

        $importerFacture = new CPPImporterFacture($this->getObjectInstancier());
        $importerFacture->setConnecteurId('PortailFacture', $id_ce);
        return $id_ce;
    }

    /**
     * @param $min_date_depot_recu
     * @param $min_date_depot_travaux
     * @param string $statut_facture
     */
    private function mockCPPWrapper(
        $min_date_depot_recu,
        $min_date_depot_travaux,
        $statut_facture = 'MISE_A_DISPOSITION'
    ) {
        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapper->expects($this->any())
            ->method('rechercheFactureParRecipiendaire')
            ->willReturn($this->getrechercheFactureParRecipiendaire($min_date_depot_recu, $statut_facture));
        $cppWrapper->expects($this->any())
            ->method('rechercheFactureTravaux')
            ->willReturn($this->getrechercheFactureTravaux($min_date_depot_travaux));
        $cppWrapper->expects($this->any())
            ->method('telechargerGroupeFacture')
            ->willReturn(file_get_contents(self::FICHIER_PIVOT));
        $cppWrapper->expects($this->any())
            ->method('consulterHistoriqueFacture')
            ->willReturn($this->getConsulterHistoriqueFacture("2018-04-19 11:16", "MISE_A_DISPOSITION"));

        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);
    }
}
