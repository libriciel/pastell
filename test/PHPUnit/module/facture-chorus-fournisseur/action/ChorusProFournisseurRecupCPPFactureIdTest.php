<?php

class ChorusProFournisseurRecupCPPFactureIdTest extends ExtensionCppTestCase
{
    /**
     * @throws Exception
     */
    public function testChorusProFournisseurRecupCPPFactureId()
    {

        $consulterCompteRenduImport_result =  [
            'codeRetour' => 0,
            'libelle' => 'TRA_MSG_00.000',
            'codeInterfaceDepotFlux' => 'FSO1113A',
            'dateDepotFlux' => '2019-03-27T11:12:24.777+01:00',
            'dateHeureEtatCourantFlux' => '2019-03-27T11:12:24.777+01:00',
            'etatCourantDepotFlux' => 'IN_INTEGRE',
            'nomFichier' => 'FSO1113A_CPP001_CPP0011113000000000044750'
        ];

        $getInfoByNumeroFluxDepot_result =  [
            'identifiantFactureCPP' => 3125108,
            'statut' => 'MISE_A_DISPOSITION'
        ];

        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapper->expects($this->any())->method('consulterCompteRenduImport')->willReturn($consulterCompteRenduImport_result);
        $cppWrapper->expects($this->any())->method('getInfoByNumeroFluxDepot')->willReturn($getInfoByNumeroFluxDepot_result);

        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);

        $this->createCppConnector("facture-chorus-fournisseur");
        $document = $this->createDocument("facture-chorus-fournisseur");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);

        $donneesFormulaire->setData('numero_flux_depot', "CPP0011113000000000044750");
        $donneesFormulaire->addFileFromCopy('fichier_facture_pdf', 'Facture-IN_DP_E2_CPP_FACTURE_MIN.xml', self::FICHIER_FACTURE_XML);

        $actionResult = $this->triggerActionOnDocument($document['id_d'], 'recup-facture-cpp-id');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);

        $this->assertTrue($actionResult);
        $this->assertEquals('Facture-IN_DP_E2_CPP_FACTURE_MIN.xml', $donneesFormulaire->getFileName('fichier_original', 0));
        $this->assertLastMessage("Identifiant de la facture 3125108 récupéré");
    }
}
