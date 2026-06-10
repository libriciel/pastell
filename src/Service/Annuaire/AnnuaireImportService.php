<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use CSV;

final class AnnuaireImportService
{
    public function __construct(
        private CSV $csv,
        private AnnuaireSQL $annuaireSQL,
        private AnnuaireGroupeSQL $annuaireGroupeSQL,
    ) {
    }

    public function import(int $id_e, string $file_path): int
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

            $this->annuaireGroupeSQL->deleteFromAllGroupes($id_a);

            foreach (array_slice($mail_info, 2) as $groupe_name) {
                $id_g = $this->annuaireGroupeSQL->getFromNom($id_e, $groupe_name);
                if (!$id_g) {
                    continue;
                }
                $this->annuaireGroupeSQL->addToGroupe($id_g, $id_a);
            }
        }
        return $nb_import;
    }
}
