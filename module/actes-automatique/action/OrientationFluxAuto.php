<?php

class OrientationFluxAuto extends ActionExecutor
{
    private function notifyAndExit($message)
    {
        $this->notify($this->action, $this->type, $message);
        throw new Exception($message);
    }

    public function go()
    {
        $action_cible = null;
        $documentActionEntite = $this->getDocumentActionEntite();
        $last_action = $documentActionEntite->getLastAction($this->id_e, $this->id_d);
        $donneesFormulaire = $this->getDonneesFormulaire();
        $envoi_signature_check = $donneesFormulaire->get('envoi_signature_check');
        $envoi_tdt = $donneesFormulaire->get('envoi_tdt');
        $envoi_ged = $donneesFormulaire->get('envoi_ged');
        $envoi_sae = $donneesFormulaire->get('envoi_sae');
        switch ($last_action) {
            case 'importation':
                if (! $donneesFormulaire->isValidable()) {
                    $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
                }
                if ($envoi_signature_check == true) {
                    $this->getDonneesFormulaire()->setData('envoi_signature', true);
                    $action_cible = 'prepare-iparapheur';
                } elseif ($envoi_tdt == true) {
                    $action_cible = 'prepare-tdt';
                } elseif ($envoi_ged == true) {
                    $action_cible = 'prepare-ged';
                } elseif ($envoi_sae == true) {
                    $action_cible = 'prepare-sae';
                } else {
                    $action_cible = 'termine';
                }
                break;
            case 'recu-iparapheur':
                if ($envoi_tdt == true) {
                    $action_cible = 'prepare-tdt';
                } elseif ($envoi_ged == true) {
                    $action_cible = 'prepare-ged';
                } elseif ($envoi_sae == true) {
                    $action_cible = 'prepare-sae';
                } else {
                    $action_cible = 'termine';
                }
                break;
            case 'acquiter-tdt':
                if ($envoi_ged == true) {
                    $action_cible = 'prepare-ged';
                } elseif ($envoi_sae == true) {
                    $action_cible = 'prepare-sae';
                } else {
                    $action_cible = 'termine';
                }
                break;
            case 'info-tdt':
                if ($envoi_ged == true) {
                    $action_cible = 'prepare-ged';
                } elseif ($envoi_sae == true) {
                    $action_cible = 'prepare-sae';
                } else {
                    $action_cible = 'termine';
                }
                break;//info-tdt
            case 'send-ged':
                if ($envoi_sae == true) {
                    $action_cible = 'prepare-sae';
                } else {
                    $action_cible = 'termine';
                }
                break;
            default:
                $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
        }

        $this->getActionCreator()->addAction($this->id_e, $this->id_u, $action_cible, "Préparation de l'envoi suivant");
        $this->setLastMessage("Préparation pour le prochain état : $action_cible");
        return true;
    }
}
