<?php

class FournisseurCommandeRenvoyerMail extends ActionExecutor
{
    private function getMailSecConnecteur(): MailSec
    {
        /** @var MailSec $connector */
        $connector = $this->getConnecteur(MailsecConnecteur::CONNECTEUR_TYPE_ID);
        return $connector;
    }

    /**
     * @throws Exception
     */
    public function go()
    {
        $recuperateur = new Recuperateur($_POST);
        $id_de = $recuperateur->getInt('id_de');

        if ($id_de) {
            $this->getMailSecConnecteur()->sendOneMail($this->id_e, $this->id_d, $id_de);
        } else {
            $this->getMailSecConnecteur()->resendUnopenedEmails($this->id_e, $this->id_d);
        }
        $this->setLastMessage("Un email a été renvoyé au founisseur");

        return true;
    }
}
