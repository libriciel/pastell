<?php

use Pastell\Service\Document\DocumentEmailRecipientType;
use Pastell\Service\Document\DocumentEmailService;

class FournisseurCommandeEnvoiMail extends ActionExecutor
{
    /**
     * @throws DonneesFormulaireException
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function go()
    {
        /** @var MailSec $mailsec */
        $mailsec = $this->getConnecteur('mailsec');
        $this->getDonneesFormulaire()->setData('has_message', true);
        $this->getDonneesFormulaire()->setData('objet', $this->getDonneesFormulaire()->get('libelle'));
        $this->getDonneesFormulaire()->setData('to', $this->getDonneesFormulaire()->get('mail_fournisseur'));
        $this->getDonneesFormulaire()->setData('message', "Veuillez trouver ci-joint le bon de commande.");
        $commande_file_name = $this->getDonneesFormulaire()->getFileName('commande');
        $this->getDonneesFormulaire()->addFileFromCopy(
            'document_attache',
            $commande_file_name,
            $this->getDonneesFormulaire()->getFilePath('commande')
        );


        $this->objectInstancier->getInstance(DocumentEmailService::class)->addRecipient(
            $this->id_d,
            $this->getDonneesFormulaire()->get('mail_fournisseur'),
            DocumentEmailRecipientType::TO
        );

        $mailsec->sendAllMail($this->id_e, $this->id_d);

        $this->addActionOK("Le document a été envoyé au fournisseur");
        return true;
    }
}
