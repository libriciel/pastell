<?php

class RecupPESRetourAll extends ActionExecutor
{
    public function go()
    {
        $entiteSQL = $this->objectInstancier->getInstance(EntiteSQL::class);

        $all_col = $entiteSQL->getAllByType(EntiteSQL::TYPE_COLLECTIVITE);
        $all_col = array_merge($all_col, $entiteSQL->getAllByType(EntiteSQL::TYPE_CENTRE_DE_GESTION));

        $envoye = [];
        foreach ($all_col as $infoCollectivite) {
            try {
                $tdT = $this->objectInstancier->getInstance(ConnecteurFactory::class)->getConnecteurByType(
                    $infoCollectivite['id_e'],
                    'helios-pes-retour',
                    'TdT'
                );
                if (!$tdT) {
                    continue;
                }
                /** @var S2low $tdT */
                $tdT->getPESRetourListe();
                $envoye[] = "{$infoCollectivite['denomination']}  : Les fichiers Hélios PES Retour ont été récupérés";
            } catch (Exception $e) {
                $envoye[] = "{$infoCollectivite['denomination']}  : " . ($e->getMessage());
                continue;
            }
        }

        $this->setLastMessage("Résultat :<br/>" . implode("<br/>", $envoye));
        return true;
    }
}
