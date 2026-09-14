<?php

class FactureCPPSAETest extends ExtensionCppTestCase
{
    public static function getModuleDataProvider()
    {
        return [
            'facture-cpp' => [
                "facture-cpp"
            ],
            'facture-chorus-fournisseur' => [
                "facture-chorus-fournisseur"
            ],
        ];
    }

    /**
     * @dataProvider getModuleDataProvider
     * @throws Exception
     */
    public function testEtapeSAE($module)
    {
        $info_connecteur = $this->createConnector(SedaNG::CONNECTEUR_ID, "Bordereau SEDA");
        $this->associateFluxWithConnector($info_connecteur['id_ce'], $module, "Bordereau SEDA");

        $connecteurInfo = $this->getDonneesFormulaireFactory()->getConnecteurEntiteFormulaire($info_connecteur['id_ce']);

        $connecteurInfo->addFileFromCopy('schema_rng', 'schema_rng.rng', __DIR__ . "/../profil/Profil_facture_Chorus_schema.rng");
        $connecteurInfo->addFileFromCopy('profil_agape', 'profil_agape.xml', __DIR__ . "/../profil/Profil_facture_Chorus.xml");
        $connecteurInfo->addFileFromData(
            'connecteur_info_content',
            'connecteur_info_content.json',
            json_encode([
                'id_service_versant' => 'FRVERSANT001',
                'id_service_archive' => 'FRAD001',
                'accord_versement' => 'ACCORD001'
            ])
        );

        $info_connecteur = $this->createConnector('fakeSAE', "SAE");
        $this->associateFluxWithConnector($info_connecteur['id_ce'], $module, "SAE");

        $info = $this->createDocument($module);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);

        $donneesFormulaire->setTabData([
            'envoi_sae' => '1',
            'has_fichier_chorus' => '1'
        ]);

        $donneesFormulaire->addFileFromCopy('fichier_facture', 'pivot.xml', self::FICHIER_PIVOT);
        $donneesFormulaire->addFileFromCopy('facture_pj_01', 'facture.pdf', self::FICHIER_FACTURE_PDF);
        $donneesFormulaire->addFileFromCopy('fichier_facture_pdf', 'facture.pdf', self::FICHIER_FACTURE_PDF);

        $this->triggerActionOnDocument($info['id_d'], "send-archive");

        $this->assertLastMessage("Le document a été envoyé au SAE");

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($info['id_d']);

        $this->assertEquals(
            '{"fournisseur_type_id":"1","type_identifiant":"1","fournisseur":"38947246500027","fournisseur_raison_sociale":"OST DEVELOPPEMENT",' .
                '"fournisseur_code_pays":"FR","fournisseur_ref_bancaire_type":"","fournisseur_ref_bancaire_compte":"","fournisseur_ref_bancaire_etablissement":"",' .
                '"fournisseur_mode_emission":"flux","siret":"26330582300019","destinataire_nom":"CHU BORDEAUX","service_destinataire_code":"EJ","no_facture":"FAC19-2512",' .
                '"facture_type":"380","type_facture":"380","facture_cadre":"A1","date_facture":"2019-03-21","facture_date_reception":"2019-03-21","facture_mode_paiement_code":"31",' .
                '"facture_mode_paiement_libelle":"VIREMENT","facture_devise":"EUR","facture_montant_ht":"828.43","montant_ttc":"873.99","facture_montant_net":"873.99",' .
                '"facture_numero_marche":"175191\/0\/175191","facture_numero_engagement":"3004564"}',
            $donneesFormulaire->getFileContent('sae_config')
        );

        $sae_archive = $donneesFormulaire->getFileContent('sae_archive');

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        file_put_contents("$tmp_folder/archive.tgz", $sae_archive);
        exec("tar xvzf $tmp_folder/archive.tgz -C $tmp_folder 2>/dev/null");

        $this->assertEquals(
            [
                '.',
                '..',
                'archive.tgz',
                'facture.pdf',
                'pivot.xml'
            ],
            scandir("$tmp_folder/")
        );

        $tmpFolder->delete($tmp_folder);

        $this->triggerActionOnDocument($info['id_d'], "verif-sae");
        $this->assertLastMessage("Récupération de l'accusé de réception : Acknowledgement - Ce transfert d'archive a été envoyé à un connecteur bouchon SAE !");

        $this->triggerActionOnDocument($info['id_d'], "validation-sae");
        $this->assertLastMessage("La transaction a été acceptée par le SAE");
    }
}
