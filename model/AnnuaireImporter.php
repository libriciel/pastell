<?php

class AnnuaireImporter
{
    private $csv;
    private $annuaireSQL;
    private $annuaireGroupeSQL;

    public function __construct(CSV $csv, AnnuaireSQL $annuaireSQL, AnnuaireGroupe $annuaireGroupeSQL)
    {
        $this->csv = $csv;
        $this->annuaireSQL = $annuaireSQL;
        $this->annuaireGroupeSQL = $annuaireGroupeSQL;
    }

    public function import($id_e, $file_path)
    {
        $mail_list = $this->csv->get($file_path, ',');

        $nb_import = 0;
        foreach ($mail_list as $mail_info) {
            if (count($mail_info) < 2) {
                continue;
            }
            if (!filter_var($mail_info[0], FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $id_a = $this->annuaireSQL->add($id_e, $mail_info[1], $mail_info[0]);
            $nb_import++;

            $this->annuaireGroupeSQL->deleleteFromAllGroupe($id_a);

            $mail_info = array_slice($mail_info, 2);
            foreach ($mail_info as $groupe_name) {
                $id_g = $this->annuaireGroupeSQL->getFromNom($groupe_name);
                if (! $id_g) {
                    continue;
                }
                $this->annuaireGroupeSQL->addToGroupe($id_g, $id_a);
            }
        }
        return $nb_import;
    }
}
