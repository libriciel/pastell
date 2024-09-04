<?php

use Mailsec\MailsecManager;

class MailsecRenvoyer extends ConnecteurTypeActionExecutor
{
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

    private function getMailsecManager(): MailsecManager
    {
        return $this->objectInstancier->getInstance(MailsecManager::class);
    }

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Throwable
     */
    public function go()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_de = $recuperateur->getInt('id_de');

        if ($id_de) {
            $this->setLastMessage("Un email a été renvoyé au destinataire");
            $this->getMailSecConnecteur()->sendOneMail($this->id_e, $this->id_d, $id_de);
        } else {
            $this->getMailSecConnecteur()->sendAllMail($this->id_e, $this->id_d);
            $this->addActionOK("Un email a été renvoyé à tous les destinataires");
        }
        $this->getMailsecManager()->updateReceipt($this->id_d);
        return true;
    }
}
