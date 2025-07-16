<?php

class HeliosOrientationFluxAuto extends ActionExecutor
{
    /**
     * @throws Exception
     */
    private function notifyAndExit(string $message): void
    {
        $this->notify($this->action, $this->type, $message);
        throw new Exception($message);
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
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
                $this->objectInstancier
                    ->getInstance(ActionExecutorFactory::class)
                    ->executeOnDocumentCritical($this->id_e, 0, $this->id_d, 'fichier_pes_change');
                $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($this->id_d);

                if (!$donneesFormulaire->isValidable()) {
                    $this->notifyAndExit("Impossible de faire avancer le document depuis l'état : $last_action");
                }
                if ($envoi_signature_check == true) {
                    $donneesFormulaire->setData('envoi_signature', true);
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
            case 'info-tdt':
                if ($envoi_ged == true) {
                    $action_cible = 'prepare-ged';
                } elseif ($envoi_sae == true) {
                    $action_cible = 'prepare-sae';
                } else {
                    $action_cible = 'termine';
                }
                break;
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
