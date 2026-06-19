<?php

/** @deprecated Since 4.1.20, Unused, Use AnnuaireImportService instead */
class AnnuaireImporter
{
    private $csv;
    private $annuaireSQL;
    private $annuaireGroupe;

    public function __construct(CSV $csv, AnnuaireSQL $annuaireSQL, AnnuaireGroupe $annuaireGroupe)
    {
        $this->csv = $csv;
        $this->annuaireSQL = $annuaireSQL;
        $this->annuaireGroupe = $annuaireGroupe;
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
            $id_a = $this->annuaireSQL->getFromEmail($id_e, $mail_info[0]);
            if ($id_a) {
                $this->annuaireSQL->edit($id_a, $mail_info[1], $mail_info[0]);
            } else {
                $id_a = $this->annuaireSQL->add($id_e, $mail_info[1], $mail_info[0]);
            }
            $nb_import++;

            $this->annuaireGroupe->deleleteFromAllGroupe($id_a);

            $mail_info = array_slice($mail_info, 2);
            foreach ($mail_info as $groupe_name) {
                $id_g = $this->annuaireGroupe->getFromNom($groupe_name);
                if (! $id_g) {
                    continue;
                }
                $this->annuaireGroupe->addToGroupe($id_g, $id_a);
            }
        }
        return $nb_import;
    }
}
