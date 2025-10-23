<?php

class PESMarcheOrientation extends ActionExecutor
{
    public function go()
    {
        $last_action = $this->getDocumentActionEntite()->getLastAction($this->id_e, $this->id_d);

        if (! $last_action) {
            throw new Exception("Erreur : la dernière action de ce dossier n'a pas été récupéré");
        }

        $next_action = $this->getNextAction($last_action);

        $message = "Changement d'état : {$last_action} -> {$next_action}";
        $this->changeAction($next_action, $message);
        $this->notify($next_action, $this->type, $message);
        return true;
    }

    private function getNextAction($action)
    {


        if ($action == 'modification') {
            return 'prepare-affectation';
        }

        if (($action == 'affectation') || ($action == 'affectation-orientation')) {
            if (!$this->getDonneesFormulaire()->isValidable()) {
                $message = "Le dossier n'est pas valide : " . $this->getDonneesFormulaire()->getLastError();
                $this->changeAction('erreur-orientation', $message);
                throw new Exception($message);
            }
            if ($this->getDonneesFormulaire()->get('envoi_tdt')) {
                return 'preparation-send-tdt';
            }
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'preparation-send-ged';
            }
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }

        if (($action == 'acquiter-tdt') || ($action == 'info-tdt')) {
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'preparation-send-ged';
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
        throw new Exception("L'action suivante de $action n'est pas défini. Arrêt du cheminement du dossier");
    }
}
