<?php

class FournisseurCommandeOrientation extends ActionExecutor
{
    public function go()
    {

        $last_action = $this->getDocumentActionEntite()->getLastAction($this->id_e, $this->id_d);

        if (! $last_action) {
            throw new Exception("Erreur : la dernière action de ce document n'a pas été récupéré");
        }

        if ($this->getDonneesFormulaire()->get('envoi_auto')) {
            $next_action = $this->getNextActionAuto($last_action);
            $message = "Changement d'état : {$last_action} -> {$next_action}";
        } else {
            $next_action = $this->getNextActionEtat($last_action);
            $message = "Conservation de l'état : {$last_action} -> {$next_action}";
        }

        $this->changeAction($next_action, $message);

        $this->notify($next_action, $this->type, $message);
        $this->setLastMessage($message);
        return true;
    }

    private function getNextActionAuto($action)
    {

        if (($action == 'modification') || ($action == 'importation')) {
            if ($this->getDonneesFormulaire()->get('envoi_signature')) {
                return 'prepare-iparapheur';
            }
            if ($this->getDonneesFormulaire()->get('envoi_mailsec')) {
                return 'prepare-envoi-mail';
            }
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'prepare-ged';
            }
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }
        if ($action == 'recu-iparapheur') {
            if ($this->getDonneesFormulaire()->get('envoi_mailsec')) {
                return 'prepare-envoi-mail';
            }
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'prepare-ged';
            }
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }
        if (in_array($action, ['reception','non-recu','erreur'])) {
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'prepare-ged';
            }
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }
        if ($action == 'send-ged') {
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }
        if ($action == 'accepter-sae') {
            return "termine";
        }
        throw new Exception("L'action suivante de $action n'est pas défini. Arret du flux");
    }

    private function getNextActionEtat($action)
    {

        if (($action == 'modification') || ($action == 'importation')) {
            return "modification";
        }
        if ($action == 'recu-iparapheur') {
            return "recu-iparapheur-etat";
        }
        if (in_array($action, ['reception','non-recu','erreur'])) {
            return "reception-etat";
        }
        if ($action == 'send-ged') {
            return "send-ged-etat";
        }
        if ($action == 'accepter-sae') {
            return "termine";
        }
        throw new Exception("L'action suivante de $action n'est pas défini. Arret du flux");
    }
}
