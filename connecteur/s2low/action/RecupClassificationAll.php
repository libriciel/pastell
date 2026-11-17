<?php

use Pastell\Service\Entite\RootEntity;

class RecupClassificationAll extends ActionExecutor
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
            $classification = new TdtClassification($tdt);
            try {
                $classificationFile = $classification->getClassificationFile();
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
