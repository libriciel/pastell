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

        $utilisateurSQL = $this->objectInstancier->getInstance(UtilisateurSQL::class);
        $oldEmail = $utilisateurSQL->getInfo($id_u)['email'];
        $utilisateurSQL->setEmail($id_u, $email);
        $this->objectInstancier->getInstance(NotificationDigestSQL::class)->updateEmail($oldEmail, $email);

        $templatedEmail = (new TemplatedEmail())
            ->to($email)
            ->subject('[Pastell] Votre changement de mail a été accepté')
            ->htmlTemplate('changement-email-accepter.html.twig')
            ->context(["message" => $message]);
        $this->objectInstancier
            ->getInstance(Mailer::class)
            ->send($templatedEmail);

        $this->addActionOK("Changement d'email accepté");
        return true;
    }
}
