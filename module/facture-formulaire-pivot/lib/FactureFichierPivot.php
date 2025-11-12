<?php

use Pastell\Service\ChorusPro\ChorusProXSDPivot;

/**
 * @deprecated 5.0.0 Chorus
 */

class FactureFichierPivot
{
    public function getXSDFichierPivot()
    {
        $xsdPivot = new ChorusProXSDPivot();
        return $xsdPivot->getSchemaPath();
    }

    public function getNameXSDFichierPivot()
    {
        return basename($this->getXSDFichierPivot());
    }

    public function checkInformation(array $information)
    {
        $info = ['siret','fournisseur','no_facture','date_facture','montant_ttc'];
        foreach ($info as $key) {
            if (empty($information[$key])) {
                throw new Exception("Impossible de générer le fichier facture PIVOT: le paramètre $key est manquant. ");
            }
        }
    }

    private function getDocument($file_path, $file_name, $num_pj, $type_pj, $minetype)
    {

        $document = new ZenXML("PJ");
        $document[$num_pj]['NumOrdre'] = $num_pj + 1;
        $document[$num_pj]->Contenu = base64_encode(file_get_contents($file_path));
        $document[$num_pj]->NomPJ = $file_name;
        $document[$num_pj]->TypePJ = $type_pj; //01-Facture/avoir- 02-Pièce jointe complémentaire
        $document[$num_pj]->MimeTypePJ = $minetype; //Conforme aux RFC2045 et RFC2046
        //Les formats de PJ acceptés sont les suivants :
        //.BMP, .GIF, .FAX, .ODT, .PPT, .TIFF, .XLS, .BZ2, .GZ, .JPEG, .P7S, .RTF, .TXT, .XML, .CSV, .GZIP, .JPG, .PDF,
        //.SVG, .XHTML, .XLSX, .DOC, .HTM, .ODP, .PNG, .TGZ, .XLC, .ZIP, .DOCX, .HTML, .ODS, .PPS, .TIF, .XLM, .PPTX.



        return $document;
    }

    public function getFichierPivot(array $docInfo): string
    {
        $this->checkInformation($docInfo);

        $cppFacturePivot = new ZenXML('CPPFacturePivot');
        $cppFacturePivot['xmlns:xsi'] = 'http://www.w3.org/2001/XMLSchema-instance';

        $cppFacturePivot['xsi:noNamespaceSchemaLocation'] = $this->getNameXSDFichierPivot();

        //EnveloppeUnitaire
        $enveloppe = $cppFacturePivot->get('Enveloppe');
        $enveloppeUnitaire = $enveloppe->get('EnveloppeUnitaire');
        $enveloppeUnitaire['NumOrdre'] = '1';

        //Parametres
        $parametre = $enveloppeUnitaire->get('Parametres');
        $parametre->ParametreIndiv[0]['NumOrdre'] = "0001";
        $parametre->ParametreIndiv[0]->Code = "DtPrd";
        $parametre->ParametreIndiv[0]->Valeurparametre = date('Y-m-d', strtotime($docInfo['date_production']));
        $parametre->ParametreIndiv[1]['NumOrdre'] = "0002";
        $parametre->ParametreIndiv[1]->Code = "IdFlx";
        $parametre->ParametreIndiv[1]->Valeurparametre = $docInfo['id_flux'];

        //Partenaires
        $partenaires = $enveloppeUnitaire->get('Partenaires');
        $partenaires->Recepteur->Id = $docInfo['recepteur_id'];
        $partenaires->Emetteur->Id = $docInfo['emetteur_id'];

        //CPPFacturePivotUnitaire
        $cppFactures = $cppFacturePivot->get('CPPFactures');
        $cppFactures['Compteur'] = 1;

        $cppFacturePivotUnitaire = $cppFactures->get('CPPFacturePivotUnitaire');
        $cppFacturePivotUnitaire['NumOrdre'] = 1;

        //Fournisseur
        $fournisseur = $cppFacturePivotUnitaire->get('Fournisseur');
        $fournisseur->TypeIdentifiant = $docInfo['fournisseur_type_id'];
        $fournisseur->Identifiant = $docInfo['fournisseur'];
        $fournisseur->RaisonSociale = htmlspecialchars($docInfo['fournisseur_raison_sociale'], ENT_QUOTES, "ISO8859-1");
        $fournisseur->CodePays = $docInfo['fournisseur_code_pays'];
        if ($docInfo['fournisseur_ref_bancaire_type']) {
            $fournisseur->ReferenceBancaire['Type'] = $docInfo['fournisseur_ref_bancaire_type'];
        }
        $fournisseur->ReferenceBancaire->Compte = $docInfo['fournisseur_ref_bancaire_compte'];
        $fournisseur->ReferenceBancaire->Etablissement = $docInfo['fournisseur_ref_bancaire_etablissement'];
        $fournisseur->ModeEmission = $docInfo['fournisseur_mode_emission'];

        //Debiteur
        $debiteur = $cppFacturePivotUnitaire->get('Debiteur');
        $debiteur->TypeIdentifiant = 1; //Valeur possible: 1 pour SIRET
        $debiteur->Identifiant = $docInfo['siret'];
        $debiteur->CodeService = $docInfo['service_destinataire_code'];

        //Donnees facture
        $donneesFacture = $cppFacturePivotUnitaire->get('DonneesFacture');
        $donneesFacture->Id = $docInfo['no_facture'];
        $donneesFacture->Type = $docInfo['facture_type'];
        $donneesFacture->Cadre = $docInfo['facture_cadre'];
        $donneesFacture->DateEmissionFacture = date('Y-m-d', strtotime($docInfo['date_facture']));
        $donneesFacture->ModePaiement->Code = $docInfo['facture_mode_paiement_code'];
        $donneesFacture->ModePaiement->Libelle = $docInfo['facture_mode_paiement_libelle'];
        $donneesFacture->DateReception = date('Y-m-d', strtotime($docInfo['facture_date_reception']));
        $donneesFacture->Devise = $docInfo['facture_devise'];
        $donneesFacture->Montants->MontantHT = $docInfo['facture_montant_ht'];
        $donneesFacture->Montants->MontantTTC = $docInfo['montant_ttc'];
        $donneesFacture->Montants->MontantNetAPayer = $docInfo['facture_montant_net'];
        $donneesFacture->Engagement->NumeroMarche = $docInfo['facture_numero_marche'];
        $donneesFacture->Engagement->NumeroEngagement = $docInfo['facture_numero_engagement'];

        //Lignes facture
        $i = 0;
        foreach ($docInfo['facture_lignes'] as $ligneInfo) {
            $donneesFacture->Lignes->Ligne[$i]['NumOrdre'] = $i + 1;
            $donneesFacture->Lignes->Ligne[$i]->ReferenceProduit = htmlspecialchars($ligneInfo['ligne_ref_produit'], ENT_QUOTES, "ISO8859-1");
            $donneesFacture->Lignes->Ligne[$i]->PrixUnitaire = $ligneInfo['ligne_prix_unitaire'];
            $donneesFacture->Lignes->Ligne[$i]->Quantite = $ligneInfo['ligne_quantite'];
            $donneesFacture->Lignes->Ligne[$i]->MontantHT = $ligneInfo['ligne_montant_ht'];
            $donneesFacture->Lignes->Ligne[$i]->TauxTVA = $ligneInfo['ligne_tva'];

            $i++;
        }

        $i = 0;
        //Fichier facture pdf
        if ($docInfo['facture_pj_01']) {
            $cppFacturePivotUnitaire->get('PJ')[$i] = $this->getDocument($docInfo['facture_pj_01']['path'], $docInfo['facture_pj_01']['name'], $i, '01', $docInfo['facture_pj_01']['type']);
            $i++;
        }
        //Pieces jointes complémentaires
        foreach ($docInfo['facture_pj_02'] as $fileInfo) {
            $cppFacturePivotUnitaire->get('PJ')[$i] = $this->getDocument($fileInfo['path'], $fileInfo['name'], $i, '02', $fileInfo['type']);
            $i++;
        }

        // CycleDeValidation
        // Non implémenté car optionel et "Présent que lorsque les cadre de facturations nécessitent une validation par un acteur du portail"

        return $cppFacturePivot->asXML();
    }
}
