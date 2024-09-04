<?php

use Mailsec\MailsecManager;

class MailsecEnvoyer extends ConnecteurTypeActionExecutor
{
    private const SENT_MAIL_NUMBER_FIELD = 'sent_mail_number';
    private const SEND_MAILSEC_ERROR = 'send-mailsec-error';

    private function getDocumentEmail(): DocumentEmail
    {
        return $this->objectInstancier->getInstance(DocumentEmail::class);
    }

    private function getMailsecManager(): MailsecManager
    {
        return $this->objectInstancier->getInstance(MailsecManager::class);
    }

    /**
     * @return AnnuaireRoleSQL
     */
    private function getAnnuaireRoleSQL(): AnnuaireRoleSQL
    {
        return $this->objectInstancier->getInstance(AnnuaireRoleSQL::class);
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function getMailSecConnecteur(): MailSec
    {
        /** @var MailSec $connector */
        $connector = $this->getConnecteur(MailsecConnecteur::CONNECTEUR_TYPE_ID);
        return $connector;
    }

    private function add2SendEmail($to, $type)
    {
        if ($this->getDocumentEmail()->getKey($this->id_d, $to)) {
            return;
        }
        $this->getDocumentEmail()->add($this->id_d, $to, $type);
    }

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Throwable
     */
    public function go()
    {
        $numberOfRecipients = 0;
        foreach (['to', 'cc', 'bcc'] as $type) {
            $typeMapped = $this->getMappingValue($type);

            $mail_to_send = $this->getMailToSend($typeMapped);

            foreach ($mail_to_send as $mail) {
                $this->add2SendEmail($mail, $type);
                ++$numberOfRecipients;
            }
        }

        if ($numberOfRecipients === 0) {
            $this->changeAction(
                $this->getMappingValue(self::SEND_MAILSEC_ERROR),
                "Impossible d'envoyer le document car il n'y a pas de destinataires (groupe ou role vide)"
            );
            return false;
        }

        $this->getDonneesFormulaire()->setData(
            $this->getMappingValue(self::SENT_MAIL_NUMBER_FIELD),
            $numberOfRecipients
        );
        $this->getMailSecConnecteur()->sendAllMail($this->id_e, $this->id_d);

        $this->getActionCreator()->addAction(
            $this->id_e,
            $this->id_u,
            $this->action,
            'Le document a été envoyé'
        );

        $this->setLastMessage('Le document a été envoyé au(x) destinataire(s)');
        $this->getMailsecManager()->updateReceipt($this->id_d);
        return true;
    }

    /**
     * @param $type
     * @return array
     * @throws NotFoundException
     */
    private function getMailToSend($type)
    {
        $mail_to_send_list = [];
        $donneesFormulaire = $this->getDonneesFormulaire();
        $lesMails = $donneesFormulaire->getFieldData($type)->getMailList();

        foreach ($lesMails as $mail) {
            $mail_to_send = $this->explodeMailToSend($mail);
            $mail_to_send_list = array_merge($mail_to_send, $mail_to_send_list);
        }
        return $mail_to_send_list;
    }

    private function explodeMailToSend($mail)
    {
        if (preg_match("/^groupe: \"(.*)\"$/u", $mail, $matches)) {
            $mail_to_send = $this->getEmailFromGroupe($matches[1]);
        } elseif (preg_match("/^role: \"(.*)\"$/u", $mail, $matches)) {
            $mail_to_send = $this->getEmailFromRole($matches[1]);
        } elseif (
            preg_match('/^groupe hérité de (.*): "(.*)"$/u', $mail, $matches) ||
            preg_match('/^groupe global: ".*"$/u', $mail, $matches)
        ) {
            $mail_to_send = $this->getEmailFromInheritedGroup($mail);
        } elseif (
            preg_match('/^rôle hérité de .*: ".*"$/u', $mail, $matches) ||
            preg_match('/^rôle global: ".*"$/u', $mail)
        ) {
            $mail_to_send = $this->getEmailFromInheritedRole($mail);
        } else {
            $mail_to_send = [$mail];
        }
        return $mail_to_send;
    }

    private function getEmailFromGroupe($groupe_name)
    {
        $annuaireGroupe = new AnnuaireGroupe($this->getSQLQuery(), $this->id_e);
        $id_g = $annuaireGroupe->getFromNom($groupe_name);
        $utilisateur = $annuaireGroupe->getAllUtilisateur($id_g);
        return $this->getFormattedEmailList($utilisateur);
    }

    private function getEmailFromRole($role_name)
    {
        $id_r = $this->getAnnuaireRoleSQL()->getFromNom($this->id_e, $role_name);
        $utilisateur = $this->getAnnuaireRoleSQL()->getUtilisateur($id_r);
        return $this->getFormattedEmailList($utilisateur);
    }

    private function getEmailFromInheritedGroup($mail)
    {
        $annuaireGroupe = new AnnuaireGroupe($this->getSQLQuery(), $this->id_e);
        $all_ancetre = $this->getEntiteSQL()->getAncetreId($this->id_e);
        $id_g = $annuaireGroupe->getFromNomDenomination($all_ancetre, $mail);
        $utilisateur = $annuaireGroupe->getAllUtilisateur($id_g);
        return $this->getFormattedEmailList($utilisateur);
    }

    private function getEmailFromInheritedRole($mail)
    {
        $all_ancetre = $this->getEntiteSQL()->getAncetreId($this->id_e);
        $id_r = $this->getAnnuaireRoleSQL()->getFromNomDenomination($all_ancetre, $mail);
        $utilisateur = $this->getAnnuaireRoleSQL()->getUtilisateur($id_r);
        return $this->getFormattedEmailList($utilisateur);
    }

    private function getFormattedEmailList(array $utilisateur_list): array
    {
        $result = [];
        foreach ($utilisateur_list as $utilisateur_info) {
            if (empty($utilisateur_info[AnnuaireSQL::DESCRIPTION])) {
                $utilisateur_info[AnnuaireSQL::DESCRIPTION] = sprintf(
                    '%s %s',
                    $utilisateur_info['prenom'],
                    $utilisateur_info['nom']
                );
            }
            $result[] = sprintf(
                '"%s" <%s>',
                $utilisateur_info[AnnuaireSQL::DESCRIPTION],
                $utilisateur_info['email']
            );
        }
        return $result;
    }
}
