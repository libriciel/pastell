<?php

use Pastell\Service\ChorusPro\ChorusProXSDPivot;

/**
 * @deprecated 5.0.0 Chorus
 */

class FactureFormulaireCreerPivot
{
    private const DEFAULT_MODE_EMISSION = 'PDF';

    /** @var  TmpFolder */
    private $tmpFolder;

    /** @var  DonneesFormulaire */
    private $donneesFormulaire;

    /**
     * FactureFormulaireCreerPivot constructor.
     */
    public function __construct()
    {
        $this->tmpFolder = new TmpFolder();
    }

    /**
     * @param DonneesFormulaire $donneesFormulaire
     * @return string
     * @throws Exception
     */
    public function createCPPFacturePivot(DonneesFormulaire $donneesFormulaire)
    {
        $this->donneesFormulaire = $donneesFormulaire;
        $tmp_folder = $this->tmpFolder->create();

        try {
            $result = $this->goThrow($tmp_folder);
        } catch (Exception $e) {
            $this->tmpFolder->delete($tmp_folder);
            throw $e;
        }
        $this->tmpFolder->delete($tmp_folder);

        return $result;
    }

    /**
     * @param $tmp_folder
     * @return string
     * @throws DonneesFormulaireException
     * @throws Exception
     */
    private function goThrow($tmp_folder)
    {
        $file_path = '';
        $donneesFormulaire = $this->donneesFormulaire;
        $facture_lignes = [];
        if ($csv_name = $donneesFormulaire->getFileName('facture_fichier_lignes_csv')) {
            $csv_path = $donneesFormulaire->getFilePath('facture_fichier_lignes_csv');
            $facture_lignes = $this->getFactureLignesCSV($csv_name, $csv_path);
        }

        $name_pj01 = $donneesFormulaire->getFileName('facture_pj_01');
        if (!(substr($name_pj01, 0, 3) == "FAC")) {
            $name_pj01 = "FAC" . $name_pj01;
        }
        $file_path = $donneesFormulaire->copyFile('facture_pj_01', $tmp_folder, 0, "facture_pj_01");
        $facture_pj_01 = [
            'path' => $this->zipFile($file_path, $name_pj01),
            'name' => $name_pj01,
            'type' => $this->getContentType($file_path),
        ];

        $facture_pj_02 = [];
        if ($donneesFormulaire->get('facture_pj_02')) {
            foreach ($donneesFormulaire->get('facture_pj_02') as $num => $fileName) {
                $name_pj02 = $donneesFormulaire->getFileName('facture_pj_02', $num);
                if (!(substr($name_pj02, 0, 3) == "PJ0")) {
                    $name_pj02 = "PJ0" . $num . $name_pj02;
                }
                $file_path = $donneesFormulaire->copyFile('facture_pj_02', $tmp_folder, $num, "facture_pj_02" . "-" . $num);
                $facture_pj_02[] = [
                    'path' => $this->zipFile($file_path, $name_pj02),
                    'name' => $name_pj02,
                    'type' => $this->getContentType($file_path),
                ];
            }
        }

        $facture_mode_paiement_libelle = $donneesFormulaire->getFormulaire()->getField('facture_mode_paiement_libelle')->getSelect();

        @ unlink($tmp_folder . "/empty");

        $id_facture = date("YmdHis") . "_" . mt_rand(0, mt_getrandmax());

        $donneesFormulaire->setData('id_facture', $id_facture);

        $docInfo = [
            /* Données de l'enveloppe : on s'en fiche */
            'date_production' => date("Y-m-d"),
            'id_flux' => $id_facture,
            'emetteur_id' => "PASTELL",
            'recepteur_id' => "PASTELL",
            'id_facture' =>  $id_facture,

            /* Données du fournisseur */
            'fournisseur_type_id' => $donneesFormulaire->get('fournisseur_type_id'),
            'fournisseur' => $donneesFormulaire->get('fournisseur'),
            'fournisseur_raison_sociale' => $donneesFormulaire->get('fournisseur_raison_sociale'),
            'fournisseur_code_pays' => $donneesFormulaire->get('fournisseur_code_pays'),
            'fournisseur_ref_bancaire_type' => $donneesFormulaire->get('fournisseur_ref_bancaire_type'),
            'fournisseur_ref_bancaire_compte' => $donneesFormulaire->get('fournisseur_ref_bancaire_compte'),
            'fournisseur_ref_bancaire_etablissement' => $donneesFormulaire->get('fournisseur_ref_bancaire_etablissement'),
            'fournisseur_mode_emission' => self::DEFAULT_MODE_EMISSION,

            /* Données du recepteur */
            'destinataire' => $donneesFormulaire->get('destinataire'),
            'siret' => $donneesFormulaire->get('siret'),
            'service_destinataire' => $donneesFormulaire->get('service_destinataire'),
            'service_destinataire_code' => $donneesFormulaire->get('service_destinataire_code'),

            /* Données de la facture */
            'no_facture' => $donneesFormulaire->get('no_facture'),
            'facture_type' => $donneesFormulaire->get('facture_type'),
            'facture_cadre' => $donneesFormulaire->get('facture_cadre'),
            'date_facture' => $donneesFormulaire->get('date_facture'),
            'facture_date_reception' => $donneesFormulaire->get('facture_date_reception'),
            'facture_mode_paiement_code' => $donneesFormulaire->get('facture_mode_paiement_code'),
            'facture_mode_paiement_libelle' => isset($facture_mode_paiement_libelle[$donneesFormulaire->get('facture_mode_paiement_libelle')]),
            'facture_devise' => $donneesFormulaire->get('facture_devise'),
            'facture_montant_ht' => $donneesFormulaire->get('facture_montant_ht'),
            'montant_ttc' => $donneesFormulaire->get('montant_ttc'),
            'facture_numero_engagement' => $donneesFormulaire->get('facture_numero_engagement'),
            'facture_numero_marche' => $donneesFormulaire->get('facture_numero_marche'),
            'facture_montant_net' => $donneesFormulaire->get('facture_montant_net'),
            'facture_lignes' => $facture_lignes,
            'facture_pj_01' => $facture_pj_01,
            'facture_pj_02' => $facture_pj_02,
        ];

        /** @var FactureFichierPivot $pivot */
        $pivot = new FactureFichierPivot();
        $fichierPivot = $pivot->getFichierPivot($docInfo);

        if (! $fichierPivot) {
            throw new Exception("Le fichier pivot n'a pas été créé.");
        }

        $donneesFormulaire->addFileFromData('fichier_facture', "$id_facture.xml", $fichierPivot);

        $chorusProXSDPivot = new ChorusProXSDPivot();
        try {
            $chorusProXSDPivot->checkIsFormatPivot($donneesFormulaire->getFilePath('fichier_facture'));
            return 'Le fichier pivot a été créé et vérifié';
        } catch (Exception $e) {
            $donneesFormulaire->removeFile('fichier_facture');
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param $csv_name
     * @param $csv_path
     * @return array
     * @throws Exception
     */
    private function getFactureLignesCSV($csv_name, $csv_path)
    {

        $facture_lignes = [];

        if (!(substr($csv_name, -4) == ".csv")) {
            throw new Exception("Le fichier facture lignes csv n'est pas au format .csv");
        }
        $CSV = new CSV();
        $lignes = $CSV->get($csv_path, ';');
        foreach ($lignes as $ligneInfo) {
            $facture_lignes[] = [
                'ligne_ref_produit' => $ligneInfo[0],
                'ligne_prix_unitaire' => $ligneInfo[1],
                'ligne_quantite' => $ligneInfo[2],
                'ligne_montant_ht' => $ligneInfo[3],
                'ligne_tva' => $ligneInfo[4],
            ];
        }
        return $facture_lignes;
    }

    /**
     * @param $file_path
     * @return mixed
     */
    private function getContentType($file_path)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        return finfo_file($finfo, $file_path);
    }

    /**
     * @param $file_path
     * @param $file_name
     * @return string
     * @throws Exception
     */
    private function zipFile($file_path, $file_name)
    {
        $fichier_zip = $file_path . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($fichier_zip, ZipArchive::CREATE) === true) {
            $zip->addFile($file_path, $file_name);
            $zip->close();
        } else {
            throw new Exception("Impossible de zipper le fichier $file_path");
        }
        return $fichier_zip;
    }
}
