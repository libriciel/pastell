<?php

class FactureCPPIparapheurEnvoieTest extends ExtensionCppTestCase
{
    public static function getStatutDataProvider()
    {
        return [
            'Envoi' => [
                "MISE_A_DISPOSITION",
                "Le document a été envoyé au parapheur électronique",
                "send-iparapheur",
                '{"CodeService":"","NumEngagement":"","NumMarche":"","Siret":"00000000013456","MontantTtc":"20"}'
            ],
            'StatutFinal' => [
                "MISE_EN_PAIEMENT",
                "Le statut MISE_EN_PAIEMENT de la facture ne permet pas l'envoi pour validation",
                "send-iparapheur-annule",
                ""
            ],
        ];
    }


    /**
     * @dataProvider getStatutDataProvider
     * @throws NotFoundException
     */
    public function testEnvoiParapheur($statut, $last_message_expected, $last_etat_expected, $json_metadata_expected)
    {

        $connecteur_info = $this->createConnector('fakeIparapheur', "Bouchon Parapheur");
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'facture-cpp', 'signature');

        $document_info = $this->createDocument('facture-cpp');
        $id_d = $document_info['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'id_facture_cpp' => "3908491",
            'no_facture' => "FAC19-2512",
            'statut_cpp' => $statut ,
            'envoi_visa' => "On",
            'iparapheur_type' => "Facture CPP",
            'iparapheur_sous_type' => "Service Fait",
            'service_destinataire_code' => "",
            'facture_numero_engagement' => "",
            'facture_numero_marche' => "",
            'siret' => "00000000013456",
            'montant_ttc' => "20",
        ]);

        $this->triggerActionOnDocument($id_d, "send-iparapheur");

        $json_metadata = $this->getDonneesFormulaireFactory()->get($id_d)->getFileContent('json_metadata');
        $this->assertEquals($json_metadata_expected, $json_metadata);
        $this->assertLastMessage($last_message_expected);
        $this->assertLastDocumentAction($last_etat_expected, $id_d);
    }
}
