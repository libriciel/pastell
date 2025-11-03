<?php

use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

class Accepter extends ActionExecutor
{
    public function go()
    {
        $id_u = $this->getDonneesFormulaire()->get('id_u');
        $message = $this->getDonneesFormulaire()->get('message');
        $email = $this->getDonneesFormulaire()->get('email_demande');

        $this->objectInstancier->getInstance(UtilisateurSQL::class)->setEmail($id_u, $email);

        $utilisateur_info = $this->objectInstancier->getInstance(UtilisateurSQL::class)->getInfo($id_u);

        $libelle_plateforme_mail = $this->objectInstancier->getInstance(
            ConfigurationSQL::class
        )->getLibellePlateformeMail();
        $templatedEmail = new TemplatedEmail()
            ->to($utilisateur_info['email'])
            ->subject("[$libelle_plateforme_mail] Votre changement de mail a été accepté")
            ->htmlTemplate('changement-email-accepter.html.twig')
            ->context(["message" => $message]);
        $this->objectInstancier
            ->getInstance(Mailer::class)
            ->send($templatedEmail);

        $this->addActionOK("Changement d'email accepté");
        return true;
    }
}
