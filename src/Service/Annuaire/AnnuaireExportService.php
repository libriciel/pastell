<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;

final class AnnuaireExportService
{
    public function __construct(
        private AnnuaireSQL $annuaireSQL,
        private AnnuaireGroupeSQL $annuaireGroupeSQL,
    ) {
    }

    public function export(int $id_e, string $search = '', int $id_g = 0): string
    {
        $stream = fopen('php://temp', 'w');

        foreach ($this->annuaireSQL->getFilteredUtilisateur($id_e, $search, $id_g) as $utilisateur) {
            $line = [$utilisateur['email'], $utilisateur['description']];
            foreach ($this->annuaireGroupeSQL->getGroupeFromUtilisateur($utilisateur['id_a']) as $groupe) {
                $line[] = $groupe['nom'];
            }
            fputcsv($stream, $line, ',', escape: '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}
