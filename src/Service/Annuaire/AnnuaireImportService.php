<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use CSV;
use Throwable;

final class AnnuaireImportService
{
    public function __construct(
        private CSV $csv,
        private AnnuaireSQL $annuaireSQL,
        private AnnuaireGroupeSQL $annuaireGroupeSQL,
    ) {
    }

    /**
     * @return array{imported: int, ignored: int}
     */
    public function import(int $id_e, string $file_path): array
    {
        $mail_list = $this->csv->get($file_path, ',');

        $nb_import = 0;
        $nb_ignored = 0;
        foreach ($mail_list as $mail_info) {
            if (\count($mail_info) === 1 && trim((string)$mail_info[0]) === '') {
                continue;
            }
            if (\count($mail_info) < 2 || !filter_var($mail_info[0], FILTER_VALIDATE_EMAIL)) {
                $nb_ignored++;
                continue;
            }
            try {
                $this->importContact($id_e, $mail_info);
                $nb_import++;
            } catch (Throwable) {
                $nb_ignored++;
            }
        }
        return ['imported' => $nb_import, 'ignored' => $nb_ignored];
    }

    private function importContact(int $id_e, array $mail_info): void
    {
        $id_a = $this->annuaireSQL->getFromEmail($id_e, $mail_info[0]);
        if ($id_a) {
            $this->annuaireSQL->edit($id_a, $mail_info[1], $mail_info[0]);
        } else {
            $id_a = $this->annuaireSQL->add($id_e, $mail_info[1], $mail_info[0]);
        }

        $this->annuaireGroupeSQL->deleteFromAllGroupes($id_a);

        foreach (\array_slice($mail_info, 2) as $groupe_name) {
            $id_g = $this->annuaireGroupeSQL->getFromNom($id_e, $groupe_name);
            if (!$id_g) {
                continue;
            }
            $this->annuaireGroupeSQL->addToGroupe($id_g, $id_a);
        }
    }
}
