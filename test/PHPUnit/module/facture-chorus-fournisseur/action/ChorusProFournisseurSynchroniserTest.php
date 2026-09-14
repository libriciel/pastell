<?php

class ChorusProFournisseurSynchroniserTest extends ExtensionCppTestCase
{
    public static function getHistoStatutDataProvider()
    {
        return [
            'Continue' => [
                'on',
                date("Y-m-d H:i"),
                "MISE_A_DISPOSITION",
                "Le status de la facture est : MISE_A_DISPOSITION",
                '',
                "creation"
            ],
            'TermineDate' => [
                '',
                "2018-04-19 11:16",
                "MISE_A_DISPOSITION",
                "La facture est en statut MISE_A_DISPOSITION depuis plus de 30 jours : fin de synchronisation",
                '',
                "termine"
            ],
            'TermineStatut' => [
                '',
                date("Y-m-d H:i"),
                "MISE_EN_PAIEMENT",
                "La facture est en statut final MISE_EN_PAIEMENT : fin de synchronisation",
                '',
                "termine"
            ],
            'TermineParam' => [
                '',
                date("Y-m-d H:i"),
                "MISE_A_DISPOSITION",
                "La facture est en statut MISE_A_DISPOSITION : pas de récupération de statut : fin de synchronisation",
                '',
                "termine"
            ],
            'TermineEnvoiSAE' => [
                '',
                date("Y-m-d H:i"),
                "MISE_EN_PAIEMENT",
                "La facture est en statut final MISE_EN_PAIEMENT : fin de synchronisation",
                'on',
                "preparation-send-sae"
            ],
        ];
    }

    /**
     * Info problème des tests : https://github.com/mikey179/vfsStream/wiki/Known-Issues
     * => ext/zip seems not to support userland stream wrappers, so it can not be used in conjunction with vfsStream
     *
     * KO: $this->objectInstancier->{'extraireDonneesPivot'}->getAllPJ($donneesFormulaire);
     * $zip->extractTo($tmp_folder) KO avec vfs: 'ZipArchive::extractTo(): Invalid or uninitialized Zip object'
     *
     * Pour contourner:
     * - dans la classe ExtraireDonneesPivot appeler $zip = $this->objectInstancier->getInstance("ZipArchive"); plutôt que $zip = new ZipArchive();
     * pour pouvoir faire un Mock sur $zip->open...
     *
     *
     * @dataProvider getHistoStatutDataProvider
     */
    public function testSynchroniserFactureDepose($recup_statut, $date_dernier_statut, $dernier_statut, $last_message_expected, $envoi_sae, $last_etat_expected)
    {

        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cppWrapper->expects($this->any())->method('telechargerGroupeFacture')->willReturn(file_get_contents(self::FICHIER_PIVOT));
        $cppWrapper->expects($this->any())->method('consulterHistoriqueFacture')->willReturn($this->getConsulterHistoriqueFacture($date_dernier_statut, $dernier_statut));

        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);


        $tmpFolder = $this->getMockBuilder('TmpFolder')
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


        $this->createParamChorusFournisseurConnector("facture-chorus-fournisseur", $recup_statut);
        $this->createCppConnector("facture-chorus-fournisseur");
        $document = $this->createDocument("facture-chorus-fournisseur");
        $this->getDonneesFormulaireFactory()->get($document['id_d'])->setData('envoi_sae', $envoi_sae);

        $actionResult = $this->triggerActionOnDocument($document['id_d'], 'verif-statut-chorus');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);

        $documentActionEntite = $this->getObjectInstancier()->getInstance(DocumentActionEntite::class);
        $this->assertEquals(
            $last_etat_expected,
            $documentActionEntite->getLastAction(self::ID_E_COL, $document['id_d'])
        );

        $this->assertTrue($actionResult);
        $this->assertEquals('FAC38947246500027FAC19-2512.pdf', $donneesFormulaire->getFileName('fichier_facture_pdf', 0));
        $this->assertLastMessage($last_message_expected);
    }
}
