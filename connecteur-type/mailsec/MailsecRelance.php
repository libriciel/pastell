<?php

declare(strict_types=1);

class MailsecRelance extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go(): bool
    {
        $send_mailsec_action = $this->getMappingValue('send-mailsec');
        $renvoi_action = $this->getMappingValue('renvoi');
        $reception_partielle_action = $this->getMappingValue('reception-partielle');
        $prepare_renvoi_action = $this->getMappingValue('prepare-renvoi');
        $non_recu_action = $this->getMappingValue('non-recu');

        /** @var MailSec $connector */
        $connector = $this->getConnecteur(MailsecConnecteur::CONNECTEUR_TYPE_ID);

        $last_action = $this->getDocumentActionEntite()->getLastAction($this->id_e, $this->id_d);
        $action_list = $this->getDocumentActionEntite()->getAction($this->id_e, $this->id_d);
        $date_last_send = false;
        foreach ($action_list as $action_info) {
            if (in_array($action_info['action'], [$send_mailsec_action, $renvoi_action], true)) {
                $date_last_send = $action_info['date'];
            }
        }
        if (!$date_last_send) {
            throw new UnrecoverableException('Impossible de trouver la date du passage à send-mailsec');
        }

        if (
            in_array($last_action, [$send_mailsec_action, $reception_partielle_action, $renvoi_action], true)
            && $connector->mustRelance($date_last_send)
        ) {
            $message = 'Préparation du renvoi du document';
            $this->changeAction($prepare_renvoi_action, $message);
            return true;
        }

        if ($connector->mustGoToNextState($date_last_send)) {
            $this->changeOrUpdateAction($non_recu_action, 'Le temps de récupération du document est écoulé');
            $this->setLastMessage('Le document passe en non reçu !');
            return true;
        }
        $message = '';
        if (in_array($last_action, [$send_mailsec_action, $reception_partielle_action, $renvoi_action], true)) {
            $date_relance = $connector->getDateRelance($date_last_send);
            $message .= "Relance programmée le $date_relance<br/>";
        }
        $date_non_recu = $connector->getDateNextState($date_last_send);
        $message .= "Mail défini comme non-reçu le $date_non_recu<br/>";

        $this->setLastMessage($message);
        return true;
    }
}
