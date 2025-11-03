<?php

use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class Refuser extends ActionExecutor
{
    public function go()
    {
        $id_u = $this->getDonneesFormulaire()->get('id_u');
        $message = $this->getDonneesFormulaire()->get('message');

        $utilisateur_info = $this->objectInstancier->getInstance(UtilisateurSQL::class)->getInfo($id_u);

        $libelle_plateforme_mail = $this->objectInstancier->getInstance(
            ConfigurationSQL::class
        )->getLibellePlateformeMail();
        $templatedEmail = new TemplatedEmail()
            ->to($utilisateur_info['email'])
            ->subject("[$libelle_plateforme_mail] Votre changement de mail a été rejeté")
            ->htmlTemplate('changement-email-refus.html.twig')
            ->context(["message" => $message]);
        $this->objectInstancier
            ->getInstance(Mailer::class)
            ->send($templatedEmail);

        $this->addActionOK("Changement d'email rejeté");
        return true;
    }
}
