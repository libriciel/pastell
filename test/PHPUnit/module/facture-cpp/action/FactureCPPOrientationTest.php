<?php

class FactureCPPOrientationTest extends ExtensionCppTestCase
{
    /**
     * @throws NotFoundException
     */
    public function testAddMultibleModifStatut()
    {
        $this->createConnecteurForTypeDossier('facture-cpp', 'parametrage-flux-facture-cpp');
        $this->createConnecteurForTypeDossier('facture-cpp', 'FakeGED');
        $this->createCppConnector('facture-cpp');

        $document_info = $this->createDocument('facture-cpp');
        $id_d = $document_info['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'id_facture_cpp' => "3325390",
            'statut_cpp' => PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
            'statut_cible_liste' => PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
            'no_facture' => "20190627",
            'envoi_ged' => true,
            'envoi_auto' => true
        ]);

        $this->addMock(PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION);

        $this->triggerActionOnDocument($id_d, "send-ged");
        $this->assertLastMessage("Le dossier 20190627 a été versé sur le dépôt");
        $this->assertLastDocumentAction("send-ged", $id_d);

        $this->triggerActionOnDocument($id_d, 'cpp-modif-statut-demande');
        $this->triggerActionOnDocument($id_d, 'cpp-modif-statut');
        $this->assertLastDocumentAction("cpp-modif-statut-ok", $id_d);

        $this->triggerActionOnDocument($id_d, "orientation");
        $this->assertLastMessage("sélection automatique de l'action suivante");
        $this->assertLastDocumentAction("termine", $id_d);

        $this->addMock(PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT);
        $this->configureDocument($id_d, ['statut_cible_liste' => PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT]);

        $this->triggerActionOnDocument($id_d, 'cpp-modif-statut-demande');
        $this->triggerActionOnDocument($id_d, 'cpp-modif-statut');
        $this->assertLastDocumentAction("cpp-modif-statut-ok", $id_d);
        $this->assertLastMessage("La facture est en statut MISE_EN_PAIEMENT");
    }

    private function addMock(string $statut_cible): void
    {
        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cppWrapper->expects($this->any())
            ->method('telechargerGroupeFacture')
            ->willReturn(file_get_contents(self::FICHIER_PIVOT));
        $cppWrapper->expects($this->any())
            ->method('consulterHistoriqueFacture')
            ->willReturn($this->getConsulterHistoriqueFacture(date("Y-m-d H:i"), $statut_cible));
        $cppWrapper->expects($this->any())
            ->method('traiterFactureRecue')
            ->willReturn($this->getTraiterFactureRecue($statut_cible));

        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);

        $tmpFolder = $this->getMockBuilder(TmpFolder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $tmpFolder->expects($this->any())->method('create')->willReturn(self::TMP_EXTRACTED);
        $tmpFolder->expects($this->any())->method('delete')->willReturn(true);
        $this->getObjectInstancier()->setInstance(TmpFolder::class, $tmpFolder);

        $zip = $this->getMockBuilder(ZipArchive::class)
            ->disableOriginalConstructor()
            ->getMock();
        $zip->expects($this->any())->method('open')->willReturn(true);
        $zip->expects($this->any())->method('extractTo')->willReturn(true);
        $zip->expects($this->any())->method('close')->willReturn(true);
        $this->getObjectInstancier()->setInstance(ZipArchive::class, $zip);
    }
}
