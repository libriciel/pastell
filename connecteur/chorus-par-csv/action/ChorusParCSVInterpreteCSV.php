<?php

/**
 * @deprecated 5.0.0 Chorus
 */

class ChorusParCSVInterpreteCSV extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function metier(): string
    {

        $connecteur_properties = $this->getConnecteurProperties();
        $fichier_csv = $connecteur_properties->getFilePath('fichier_csv');
        if (!file_exists($fichier_csv)) {
            throw new Exception("Il n'y a pas de fichier CSV");
        }

        $CSV = new CSV();
        $colList = $CSV->get($fichier_csv, ';');

        $fichier_csv_interprete = "chorus-csv-interprete-" . date("YmdHis") . ".csv";
        $fichier_csv_interprete_lines = "";

        foreach ($colList as $col) {
            if (!$col[0]) {
                continue;
            }
            if (count($col) == 4) {
                $connecteur_properties->setData('user_login', $col[0]);
                $connecteur_properties->setData('user_password', $col[1]);

                $id_chorus_structure = $this->recupIdChorus($col[2]);
                $id_chorus_fournisseur = $this->recupIdChorus($col[3]);
                //('user_login', 'user_password', 'siret_structure',
                //'id_chorus_structure','siret_fournisseur','id_chorus_fournisseur')
                $fichier_csv_interprete_lines .= $col[0] . ';"' . $col[1] . '";' . $col[2] . ";" .
                    $id_chorus_structure . ";" . $col[3] . ";" . $id_chorus_fournisseur . "\n";
            } else {
                throw new Exception('Les lignes doivent être de la forme "utilisateur technique";
                "mot de passe";"SIRET de la structure (optionel)";"SIRET du fournisseur (optionel)"');
            }
        }
        $connecteur_properties->addFileFromData(
            "fichier_csv_interprete",
            $fichier_csv_interprete,
            $fichier_csv_interprete_lines
        );

        return $fichier_csv_interprete_lines;
    }

    /**
     * @throws Exception
     */
    public function recupIdChorus($siret)
    {
        /** @var ChorusParCsv $connecteur_chorus */
        $connecteur_chorus = $this->getMyConnecteur();
        $id_chorus = $connecteur_chorus->getIdentifiantStructureCPPByIdentifiantStructure($siret, "false");
        if ((!$id_chorus) && ($siret)) {
            throw new Exception("Le siret $siret n'a pas été trouvé. L'identifiant Chorus est invalide");
        }
        return $id_chorus;
    }

    public function go(): bool
    {
        try {
            $result = $this->metier();
        } catch (Exception $ex) {
            $this->setLastMessage("Le fichier CSV n'a pas pu être interprété: " . $ex->getMessage());
            return false;
        }
        $this->setLastMessage($result);
        return true;
    }
}
