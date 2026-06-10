<?php

class AnnuaireExporter
{
    private $csvOutput;
    private $annuaireSQL;
    private $annuaireGroupeSQL;

    public function __construct(CSVoutput $csvOutput, AnnuaireSQL $annuaireSQL, AnnuaireGroupeSQL $annuaireGroupeSQL)
    {
        $this->csvOutput = $csvOutput;
        $this->annuaireSQL = $annuaireSQL;
        $this->annuaireGroupeSQL = $annuaireGroupeSQL;
    }


    public function export($id_e)
    {
        $utilisateur_list = $this->annuaireSQL->getUtilisateur($id_e);

        $display = [];

        foreach ($utilisateur_list as $utilisateur_info) {
            $line = [$utilisateur_info['email'],$utilisateur_info['description']];
            $groupe_list = $this->annuaireGroupeSQL->getGroupeFromUtilisateur($utilisateur_info['id_a']);
            foreach ($groupe_list as $groupe_info) {
                $line[] = $groupe_info['nom'];
            }
            $display[] = $line;
        }

        $this->csvOutput->displayHTTPHeader("pastell-annuaire-$id_e.csv");

        $this->csvOutput->begin();
        foreach ($display as $line) {
            $this->csvOutput->displayLine($line);
        }
        $this->csvOutput->end();
    }
}
