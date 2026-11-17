<?php

class MailsecRenvoyer extends ConnecteurTypeActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function getMailSecConnecteur(): MailsecConnecteur
    {
        /** @var MailsecConnecteur $connector */
        $connector = $this->getConnecteur(MailsecConnecteur::CONNECTEUR_TYPE_ID);
        return $connector;
    }

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Throwable
     * @throws Exception
     */
    public function go()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_de = $recuperateur->getInt('id_de');
        $mailsecConnecteur = $this->getMailSecConnecteur();

        if ($id_de) {
            $this->setLastMessage("Un email a été renvoyé au destinataire");
            $mailsecConnecteur->sendOneMail($this->id_e, $this->id_d, $id_de);
        } else {
            $mailsecConnecteur->resendUnopenedEmails($this->id_e, $this->id_d);
            $this->addActionOK("Un email a été renvoyé à tous les destinataires n'ayant pas ouvert le précédent");
        }
        $mailsecConnecteur->updateReceipt($this->id_d);
        return true;
    }
}
