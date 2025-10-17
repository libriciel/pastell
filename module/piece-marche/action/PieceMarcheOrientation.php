<?php

class PieceMarcheOrientation extends ActionExecutor
{
    public function go()
    {
        $last_action = $this->getDocumentActionEntite()->getLastAction($this->id_e, $this->id_d);

        if (! $last_action) {
            throw new Exception("Erreur : la dernière action de ce dossier n'a pas été récupéré");
        }

        $next_action = $this->getNextAction($last_action);

        if ($next_action == 'preparation-send-ged') {
            $this->createJournal();
        }

        $message = "Changement d'état : {$last_action} -> {$next_action}";
        $this->changeAction($next_action, $message);
        $this->notify($next_action, $this->type, $message);
        return true;
    }

    private function getNextAction($action)
    {

        if (($action == 'modification') || ($action == 'affectation') || ($action == 'affectation-orientation')) {
            if (!$this->getDonneesFormulaire()->isValidable()) {
                $message = "Le document n'est pas valide : " . $this->getDonneesFormulaire()->getLastError();
                $this->changeAction('erreur-orientation', $message);
                throw new Exception($message);
            }

            if ($this->getDonneesFormulaire()->get('envoi_signature')) {
                return 'preparation-send-iparapheur';
            }
            if ($this->getDonneesFormulaire()->get('envoi_mailsec')) {
                return 'preparation-send-mailsec';
            }
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'preparation-send-ged';
            }
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }
        if ($action == 'recu-iparapheur') {
            if ($this->getDonneesFormulaire()->get('envoi_mailsec')) {
                return 'preparation-send-mailsec';
            }
            if ($this->getDonneesFormulaire()->get('envoi_ged')) {
                return 'preparation-send-ged';
            }
            if ($this->getDonneesFormulaire()->get('envoi_sae')) {
                return 'preparation-send-sae';
            }
            return "termine";
        }
        if (in_array($action, ['reception','non-recu','erreur'])) {
            if ($action == 'reception') {
                $this->getDonneesFormulaire()->setData('is_recupere', '1');
            }
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

    private function createJournal()
    {
        $journal = $this->getJournal()->getAll($this->id_e, false, $this->id_d, 0, 0, 10000);
        foreach ($journal as $i => $journal_item) {
            $journal[$i]['preuve'] = base64_encode($journal[$i]['preuve']);
        }

        $date_journal_debut = $journal[count($journal) - 1]['date'];
        $date_cloture_journal = $journal[0]['date'];
        $journal = json_encode($journal);

        $this->getDonneesFormulaire()->addFileFromData('journal', 'journal.json', $journal);
        $this->getDonneesFormulaire()->setData('date_journal_debut', date("Y-m-d", strtotime($date_journal_debut)));
        $this->getDonneesFormulaire()->setData('date_cloture_journal', date("Y-m-d", strtotime($date_cloture_journal)));
        $this->getDonneesFormulaire()->setData('date_cloture_journal_iso8601', date('c', strtotime($date_cloture_journal)));
        $this->getDonneesFormulaire()->setData('journal_show', true);
    }
}
