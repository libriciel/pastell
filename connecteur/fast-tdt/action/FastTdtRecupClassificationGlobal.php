<?php

use Pastell\Service\Entite\RootEntity;

class FastTdtRecupClassificationGlobal extends ActionExecutor
{
    public function go()
    {
        $connecteurEntiteSql = $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class);
        $fastTdtConnectors = $connecteurEntiteSql->getAllById('fast-tdt');

        $summary = [];
        foreach ($fastTdtConnectors as $connector) {
            if ($connector['id_e'] === RootEntity::ID_E) {
                continue;
            }
            $denomination = $connector['denomination'];
            $id_ce = $connector['id_ce'];
            $message = "$denomination(id_ce=$id_ce)";

            /** @var FastTdt $tdt */
            $tdt = $this->getConnecteurFactory()->getConnecteurById($id_ce);
            $classification = new FastTdtClassification($tdt);
            try {
                $classificationFile = $classification->getClassificationFile();
                if (!$classificationFile) {
                    $summary[] = "$message : Il n'y a pas de nouvelle classification disponible";
                    continue;
                }
                $classificationDate = $classification->getClassificationDate($classificationFile);

                /** @var DonneesFormulaire $connecteur_properties */
                $connecteur_properties = $this->getConnecteurConfig($id_ce);
                $connecteur_properties->addFileFromData(
                    "classification_file",
                    "classification.xml",
                    $classificationFile
                );
                $connecteur_properties->setData("classification_date", $classificationDate);

                $summary[] = "$message : classification récupérée";
            } catch (Exception $e) {
                $summary[] = "$message : " . ($e->getMessage());
                continue;
            }
        }

        $this->setLastMessage("Résultat :<br/>" . implode("<br/>", $summary));
        return true;
    }
}
