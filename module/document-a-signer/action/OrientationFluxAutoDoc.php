<?php

class OrientationFluxAutoDoc extends ActionExecutor
{
    private function notifyAndExit($message)
    {
        $this->notify($this->action, $this->type, $message);
        throw new Exception($message);
    }

    public function go()
    {

        $documentActionEntite = $this->getDocumentActionEntite();
        $last_action = $documentActionEntite->getLastAction($this->id_e, $this->id_d);
        $donneesFormulaire = $this->getDonneesFormulaire();
        $envoi_ged = $donneesFormulaire->get('envoi_ged');
        $envoi_auto = $donneesFormulaire->get('envoi_auto');
        $action_cible = 'modification';

        if ($envoi_auto) {
            switch ($last_action) {
                case 'importation':
                    if (!$donneesFormulaire->isValidable()) {
                        $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
                    }
                    $action_cible = 'prepare-iparapheur';
                    break;
                case 'recu-iparapheur':
                    if ($envoi_ged == true) {
                        $action_cible = 'prepare-ged';
                    } else {
                        $action_cible = 'termine';
                    }
                    break;
                case 'send-ged':
                        $action_cible = 'termine';
                    break;
                default:
                    $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
            }
            $this->changeAction($action_cible, 'Affectation automatique du nouvel état');
            $this->setLastMessage("Préparation pour le prochain état : $action_cible");
            return true;
        } else {
            switch ($last_action) {
                case 'importation':
                    if (!$donneesFormulaire->isValidable()) {
                        $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
                    }
                    $action_cible = 'modification';
                    break;
                case 'recu-iparapheur':
                    $action_cible = 'recu-iparapheur-etat';
                    break;
                case 'send-ged':
                    $action_cible = 'send-ged-etat';
                    break;
                default:
                    $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
            }
            $this->changeAction($action_cible, "Conservation de l'état du document");
            $this->setLastMessage("Conservation de l'état du document : $action_cible");
            return true;
        }
    }
}
