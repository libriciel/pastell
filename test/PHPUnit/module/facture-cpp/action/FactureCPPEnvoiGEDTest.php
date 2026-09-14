<?php

class FactureCPPEnvoiGEDTest extends ExtensionCppTestCase
{
    public static function getStatutDataProvider()
    {
        return [
            'Depot' => [
                "MISE_A_DISPOSITION",
                "",
                "Le dossier FAC19-2512 a été versé sur le dépôt",
                "send-ged"
            ],
            'HasSendGED' => [
                "MISE_A_DISPOSITION",
                "1",
                "La facture a déja été déposée en GED",
                "modification"
            ],
            'StatutFinal' => [
                "MISE_EN_PAIEMENT",
                "",
                "Le statut MISE_EN_PAIEMENT de la facture ne permet pas l'envoi en GED",
                "send-ged-annule"
            ],
        ];
    }


    /**
     * @dataProvider getStatutDataProvider
     * @throws NotFoundException
     */
    public function testEnvoiGED($statut, $has_send_ged, $last_message_expected, $last_etat_expected)
    {

        $connecteur_info = $this->createConnector('parametrage-flux-facture-cpp', "Param CPP");
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'facture-cpp', 'ParametrageFlux');

        $connecteur_info = $this->createConnector('FakeGED', "Bouchon GED");
        $this->associateFluxWithConnector($connecteur_info['id_ce'], 'facture-cpp', 'GED');

        $document_info = $this->createDocument('facture-cpp');
        $id_d = $document_info['id_d'];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $donneesFormulaire->setTabData([
            'id_facture_cpp' => "3908491",
            'no_facture' => "FAC19-2512",
            'statut_cpp' => $statut ,
            'envoi_ged' => "On",
            'has_send_ged' => $has_send_ged ,
        ]);

        $this->triggerActionOnDocument($id_d, "send-ged");
        $this->assertLastMessage($last_message_expected);

        $this->assertLastDocumentAction($last_etat_expected, $id_d);
    }
}
