<?php

use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class Refuser extends ActionExecutor
{
    public function go()
    {
        $id_u = $this->getDonneesFormulaire()->get('id_u');
        $message = $this->getDonneesFormulaire()->get('message');

        $utilisateur_info = $this->objectInstancier->getInstance(UtilisateurSQL::class)->getInfo($id_u);

        $plateforme_mail = $this->objectInstancier->getInstance('plateforme_mail');
        $libelle_plateforme_mail = $this->objectInstancier->getInstance(
            ConfigurationSQL::class
        )->getLibellePlateformeMail();
        $templatedEmail = new TemplatedEmail()
            ->from(new Address($plateforme_mail, $libelle_plateforme_mail))
            ->to($utilisateur_info['email'])
            ->subject('[Pastell] Votre changement de mail a été rejeté')
            ->htmlTemplate('changement-email-refus.html.twig')
            ->context(["message" => $message]);
        $this->objectInstancier
            ->getInstance(Mailer::class)
            ->send($templatedEmail);

        $this->addActionOK("Changement d'email rejeté");
        return true;
    }
}
