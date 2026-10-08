<?php

class FactureCPPDispoGFTest extends ExtensionCppTestCase
{
    /**
     * @throws NotFoundException
     */
    public function testMiseADispoGFIsEditable()
    {
        $data =  [
            'id_facture_cpp' => "3325390",
            'statut_cpp' => "MISE_A_DISPOSITION",
            'fournisseur' => "00000000000727",
            'destinataire' => "25784152",
            'siret' => "00000000013456",
            'service_destinataire' => "",
            'service_destinataire_code' => "",
            'type_facture' => "FACTURE",
            'no_facture' => "20190627",
            'date_facture' => "2019-06-27",
            'date_depot' => "2019-06-27",
            'montant_ttc' => "20",
            'type_identifiant' => "SIRET",
            'fournisseur_raison_sociale' => "TAA001DESTINATAIRE",
            'date_mise_a_dispo' => "2019-06-27 15:00",
            'date_fin_suspension' => "",
            'date_passage_statut' => "2019-06-27 15:00",
            'type_integration' => "CPP",
            'facture_numero_engagement' => "",
            'facture_numero_marche' => "",
            'facture_cadre' => "A1",
            'envoi_visa' => "",
            'iparapheur_type' => "",
            'iparapheur_sous_type' => "",
            'envoi_ged' => "",
            'envoi_sae' => "",
            'check_mise_a_dispo_gf' => "",
            'envoi_auto' => "",
        ];

        $document = $this->createDocument("facture-cpp");
        $this->configureDocument($document['id_d'], $data, 1);
        $this->triggerActionOnDocument($document['id_d'], 'mise-a-dispo-gf');

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($document['id_d']);

        $this->assertEquals(1, $donneesFormulaire->get('has_mise_a_dispo_gf'));
        $this->assertTrue($donneesFormulaire->isEditable('statut_cible_liste'));
    }
}
