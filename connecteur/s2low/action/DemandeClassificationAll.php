<?php

use Pastell\Service\Entite\RootEntity;

class DemandeClassificationAll extends ActionExecutor
{
    public function go()
    {

        $connecteurEntiteSql = $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class);
        $s2lowTdtConnectors = $connecteurEntiteSql->getAllById('s2low');

        $summary = [];
        foreach ($s2lowTdtConnectors as $connector) {
            if ($connector['id_e'] === RootEntity::ID_E) {
                continue;
            }
            $denomination = $connector['denomination'];
            $id_ce = $connector['id_ce'];
            $message = "$denomination(id_ce=$id_ce)";

            /** @var S2low $tdt */
            $tdt = $this->getConnecteurFactory()->getConnecteurById($id_ce);
            try {
                $result = $tdt->demandeClassification();

                $summary[] = "$message : demande de classification envoyée";
            } catch (Exception $e) {
                $summary[] = "$message : " . ($e->getMessage());
                continue;
            }
        }

        $this->setLastMessage("Résultat :<br/>" . implode("<br/>", $summary));
        return true;
    }
}
