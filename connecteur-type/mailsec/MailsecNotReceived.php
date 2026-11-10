<?php

final class MailsecNotReceived extends ConnecteurTypeActionExecutor
{
    public function go(): bool
    {
        $this->objectInstancier->getInstance(DocumentEmailSQL::class)->setNotReceived($this->id_d);
        $this->addActionOK('Mail défini comme non-reçu.');
        return true;
    }
}
