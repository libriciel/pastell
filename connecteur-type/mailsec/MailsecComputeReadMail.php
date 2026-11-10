<?php

class MailsecComputeReadMail extends ConnecteurTypeActionExecutor
{
    private const READ_MAIL_NUMBER_FIELD = 'sent_mail_read';


    /**
     * @throws NotFoundException
     */
    public function go()
    {
        $documentEmailSQL = $this->objectInstancier->getInstance(DocumentEmailSQL::class);

        $this->getDonneesFormulaire()->setData(
            $this->getMappingValue(self::READ_MAIL_NUMBER_FIELD),
            $documentEmailSQL->countRead($this->id_d)
        );

        return true;
    }
}
