<?php

class StatutFactureCppSendTest extends ExtensionCppTestCase
{
    /**
     * When the change of the status of the invoice is sent to chorus
     *
     * @throws Exception
     */
    public function testWhenTheStatusChangeRequestIsSent()
    {
        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapper->expects($this->any())->method('getIdentifiantStructureCPPByIdentifiantStructure')->willReturn(1234);
        $cppWrapper->expects($this->any())->method('getCppInvoiceId')->willReturn(10000);
        // Not important in this case
        $cppWrapper->expects($this->any())->method('traiterFactureRecue')->willReturn([
            'idFacture' => 42,
            'numeroFacture' => 12,
            'dateTraitement' => '2021-12-12',
            'nouveauStatut' => 'OK'
        ]);

        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);

        $this->createCppConnector("statut-facture-cpp");
        $document = $this->createDocument("statut-facture-cpp");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);
        $donneesFormulaire->addFileFromCopy('fichier_statut_facture', 'Example01-FactureStatut-ok.xml', __DIR__ . "/../fixtures/Example01-FactureStatut-ok.xml");

        $actionResult = $this->triggerActionOnDocument($document['id_d'], 'create-statut-facture-cpp');

        $this->assertTrue($actionResult);
        $this->assertLastMessage("Importation du document Pastell");

        $actionResult = $this->triggerActionOnDocument($document['id_d'], 'send-statut-facture-cpp');

        $this->assertTrue($actionResult);
        $this->assertLastMessage("Changement du statut effectué avec succès");
    }
}
