<?php

use Pastell\Service\Document\DocumentEntiteService;

class ParametrageFluxFactureCPPSupprimer extends SupprimerFacture
{
    /**
     * @return mixed
     * @throws Exception
     */
    public function getNbJourAvantSupp()
    {
        /** @var ParametrageFluxFactureCPP $conn */
        $conn = $this->getMyConnecteur();
        return $conn->getNbJourAvantSupp();
    }

    /**
     * @param $all_id_d
     * @param $id_e
     * @return string
     */
    public function doTraitementSuppression($all_id_d, $id_e)
    {
        $action_selected = "supression";
        $error = "";
        $message = "";

        foreach ($all_id_d as $id_d) {
            $infoDocument = $this->objectInstancier->getInstance(DocumentEntiteService::class)->getInfo($id_e, $id_d);

            if ($this->objectInstancier->getInstance(JobManager::class)->hasActionProgramme($id_e, $id_d)) {
                $error .= "Il y a déjà une action programmée pour le document « {$infoDocument['titre']} »<br/>";
            }

            $message .= "L'action « $action_selected » est programmée pour le document « {$infoDocument['titre']} »<br/>";
        }

        if ($error) {
            $this->objectInstancier->getInstance(LastError::class)->setLastError($error . "<br/><br/>Aucune action n'a été executée");
            return $error;
        }

        $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeLotDocument($id_e, $this->id_u, $all_id_d, $action_selected);
        $this->objectInstancier->getInstance(LastMessage::class)->setLastMessage($message);
        return $message;
    }
}
