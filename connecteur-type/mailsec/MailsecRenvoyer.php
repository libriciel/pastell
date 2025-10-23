<?php

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

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_de = $recuperateur->getInt('id_de');

        if ($id_de) {
            $this->setLastMessage("Un email a été renvoyé au destinataire");
            $this->getMailSecConnecteur()->sendOneMail($this->id_e, $this->id_d, $id_de);
        } else {
            $this->getMailSecConnecteur()->resendUnopenedEmails($this->id_e, $this->id_d);
            $this->addActionOK("Un email a été renvoyé à tous les destinataires n'ayant pas ouvert le précédent");
        }
        return true;
    }
}
