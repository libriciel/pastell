<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use ConfigurationSQL;
use Exception;
use Pastell\Mailer\Mailer;
use Pastell\Service\TokenGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Journal;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use UtilisateurSQL;

final class PasswordResetMailService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly Journal $journal,
        private readonly TokenGenerator $tokenGenerator,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly ConfigurationSQL $configurationSQL,
        private readonly string $site_base,
        private readonly string $plateforme_mail,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function sendResetMail(int $id_u): void
    {
        $token = $this->tokenGenerator->generate();
        $info = $this->utilisateurSQL->getInfo($id_u);
        $this->utilisateurSQL->reinitPassword($id_u, $token);

        $link = \sprintf('%s/Connexion/changementMdp?mail_verif=%s', rtrim($this->site_base, '/'), $token);

        $libelle_plateforme_mail = $this->configurationSQL->getLibellePlateformeMail();
        $templatedEmail = new TemplatedEmail()
            ->from(new Address($this->plateforme_mail, $libelle_plateforme_mail))
            ->to($info['email'])
            ->subject('[Pastell] Procédure de modification de mot de passe')
            ->htmlTemplate('oublie-identifiant.html.twig')
            ->context(['link' => $link, 'login' => $info['login']]);
        $this->mailer->send($templatedEmail);
        $this->journal->addActionAutomatique(
            Journal::MODIFICATION_UTILISATEUR,
            $info['id_e'],
            0,
            'mot de passe modifié',
            "Procédure initiée pour {$info['email']}"
        );
    }
}
