<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Date;
use DocumentCreationService;
use DocumentSQL;
use DonneesFormulaireFactory;
use ForbiddenException;
use NotFoundException;
use NotificationMail;
use PasswordGenerator;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use UnrecoverableException;
use UtilisateurNewEmailSQL;
use UtilisateurSQL;

final class UserEmailChangeService
{
    private const REQUEST_VALIDITY = '-1 day';
    private const DOCUMENT_TYPE = 'changement-email';
    private const NOTIFICATION_ACTION = 'creation';

    public function __construct(
        private readonly UtilisateurNewEmailSQL $utilisateurNewEmailSQL,
        private readonly PasswordGenerator $passwordGenerator,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly DocumentCreationService $documentCreationService,
        private readonly DocumentSQL $documentSQL,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly NotificationMail $notificationMail,
    ) {
    }

    public function createRequest(int $id_u, string $email): string
    {
        $this->utilisateurNewEmailSQL->delete($id_u);
        $password = $this->passwordGenerator->getPassword();
        $this->utilisateurNewEmailSQL->insert($id_u, $email, $password, date(Date::DATE_ISO));
        return $password;
    }

    public function getValidRequest(string $password): ?array
    {
        $min_date = date(Date::DATE_ISO, strtotime(self::REQUEST_VALIDITY));
        return $this->utilisateurNewEmailSQL->getByPasswordSince($password, $min_date);
    }

    public function deleteRequest(int $id_u): void
    {
        $this->utilisateurNewEmailSQL->delete($id_u);
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     */
    public function confirmRequest(string $password): bool
    {
        $request_info = $this->getValidRequest($password);
        if ($request_info === null) {
            return false;
        }
        $id_u = (int)$request_info['id_u'];
        $utilisateur_info = $this->utilisateurSQL->getInfo($id_u);
        $id_d = $this->createChangeEmailDocument($utilisateur_info, $request_info['email']);
        $this->deleteRequest($id_u);

        $this->notificationMail->notify(
            (int)$utilisateur_info['id_e'],
            $id_d,
            self::NOTIFICATION_ACTION,
            self::DOCUMENT_TYPE,
            $utilisateur_info['login'] . " a fait une demande de changement d'email"
        );
        return true;
    }

    /**
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function createChangeEmailDocument(array $utilisateur_info, string $email): string
    {
        $id_d = $this->documentCreationService->createDocument(
            (int)$utilisateur_info['id_e'],
            (int)$utilisateur_info['id_u'],
            self::DOCUMENT_TYPE
        );
        $this->documentSQL->setTitre($id_d, $utilisateur_info['login']);

        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);
        $donneesFormulaire->setTabData([
            'id_u' => $utilisateur_info['id_u'],
            'login' => $utilisateur_info['login'],
            'nom' => $utilisateur_info['nom'],
            'prenom' => $utilisateur_info['prenom'],
            'email_actuel' => $utilisateur_info['email'],
            'email_demande' => $email,
        ]);
        return $id_d;
    }
}
