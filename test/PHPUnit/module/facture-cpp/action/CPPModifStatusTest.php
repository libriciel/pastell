<?php

class CPPModifStatusTest extends ExtensionCppTestCase
{
    private const FLUX_FACTURE_CPP = "facture-cpp";

    public static function getStatutCibleDataProvider()
    {
        return [
            'CibleEqual' => [
                'ok',
                PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
                PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
                '',
                'La facture est déja en statut MISE_A_DISPOSITION',
                'cpp-modif-statut-ok',
            ],
            'CibleNotExist' => [
                'ko',
                PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
                PortailFactureConnecteur::STATUT_ACHEMINEMENT,
                '',
                "Le statut cible EN_COURS_ACHEMINEMENT n'existe pas.",
                'cpp-modif-statut-erreur',
            ],
            'CibleExist' => [
                'ok',
                PortailFactureConnecteur::STATUT_REJETEE,
                PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
                '',
                'La facture est en statut MISE_A_DISPOSITION',
                'cpp-modif-statut-ok',
            ],
            'CibleRejeteSansMotif' => [
                'ko',
                PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
                PortailFactureConnecteur::STATUT_REJETEE,
                '',
                'Le statut cible REJETEE nécessite un motif',
                'cpp-modif-statut-erreur',
            ],
            'CibleLongMotif' => [
                'ok',
                PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
                PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT,
                'Test avec un modif de plus de 255 caractères, cf PISTE API Chorus TRAITER_FACTURE_RECUE: ' .
                    'le motif est tronqué à 255 caractères lors de la demande de modification de statut sur Chorus Pro #150, ' .
                    'Test avec un modif de plus de 255 caractères, cf PISTE API Chorus TRAITER_FACTURE_RECUE: ' .
                    'le motif est tronqué à 255 caractères lors de la demande de modification de statut sur Chorus Pro #150' ,
                'La facture est en statut MISE_EN_PAIEMENT',
                'cpp-modif-statut-ok',
            ],
        ];
    }

    /**
     * @param $result_expected
     * @param $statut
     * @param $statut_cible
     * @param $motif
     * @param $last_message_expected
     * @param $last_etat_expected
     * @throws NotFoundException
     *
     * @dataProvider getStatutCibleDataProvider
     */
    public function testModifStatut($result_expected, $statut, $statut_cible, $motif, $last_message_expected, $last_etat_expected)
    {
        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $cppWrapper->expects($this->any())->method('telechargerGroupeFacture')->willReturn(file_get_contents(self::FICHIER_PIVOT));
        $cppWrapper->expects($this->any())->method('consulterHistoriqueFacture')->willReturn($this->getConsulterHistoriqueFacture(date("Y-m-d H:i"), $statut_cible));
        $cppWrapper->expects($this->any())->method('traiterFactureRecue')->willReturn($this->getTraiterFactureRecue($statut_cible));

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



        $this->createCppConnector(self::FLUX_FACTURE_CPP);

        $document_info = $this->createDocument('facture-cpp');
        $id_d = $document_info['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'id_facture_cpp' => '3325390',
            'statut_cpp' => $statut,
            'statut_cible_liste' => $statut_cible,
            'motif_maj' => $motif,
            'fournisseur' => '00000000000727',
            'destinataire' => '25784152',
            'siret' => '00000000013456',
            'service_destinataire' => '',
            'service_destinataire_code' => '',
            'type_facture' => 'FACTURE',
            'no_facture' => '20190627',
            'date_facture' => '2019-06-27',
            'date_depot' => '2019-06-27',
            'montant_ttc' => '20',
            'type_identifiant' => 'SIRET',
            'fournisseur_raison_sociale' => 'TAA001DESTINATAIRE',
            'date_mise_a_dispo' => '2019-06-27 15:00',
            'date_fin_suspension' => '',
            'date_passage_statut' => '2019-06-27 15:00',
            'is_cpp' => '1',
            'type_integration' => 'CPP',
            'facture_numero_engagement' => '',
            'facture_numero_marche' => '',
            'facture_cadre' => 'A1',
        ]);

        $this->triggerActionOnDocument($id_d, 'cpp-modif-statut-demande');
        $actionResult = $this->triggerActionOnDocument($id_d, 'cpp-modif-statut');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        if ($result_expected == "ok") {
            $this->assertTrue($actionResult);
            $this->assertEquals($statut_cible, $donneesFormulaire->get('statut_cpp'));
        } else {
            $this->assertFalse($actionResult);
            $this->assertEquals($statut, $donneesFormulaire->get('statut_cpp'));
        }
        $this->assertLastDocumentAction($last_etat_expected, $id_d, self::ID_E_COL);
        $this->assertLastMessage($last_message_expected);
    }
}
