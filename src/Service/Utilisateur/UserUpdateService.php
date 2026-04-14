<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use ConflictException;
use Journal;
use NotificationDigestSQL;
use Pastell\Utilities\Certificate;
use Pastell\Validator\UserValidator;
use RoleUtilisateur;
use UnrecoverableException;
use UtilisateurSQL;

final class UserUpdateService
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly Journal $journal,
        private readonly UserValidator $userValidator,
        private readonly NotificationDigestSQL $notificationDigestSQL,
    ) {
    }

    /**
     * @throws ConflictException
     * @throws UnrecoverableException
     */
    public function update(
        int $userId,
        string $login,
        string $email,
        string $firstname,
        string $lastname,
        int $entityId = 0,
        ?string $password = null,
        ?string $certificateContent = null,
    ): int {
        $this->userValidator->validateExistingUser(
            $userId,
            $login,
            $email,
            $firstname,
            $lastname,
            $entityId,
            $password,
            $certificateContent
        );

        $oldInfo = $this->utilisateurSQL->getInfo($userId);

        if ($certificateContent !== null) {
            $this->utilisateurSQL->setCertificat($userId, new Certificate($certificateContent));
        }
        if ($password !== null) {
            $this->utilisateurSQL->setPassword($userId, $password);
        }
        $this->utilisateurSQL->validMailAuto($userId);
        $this->utilisateurSQL->setNomPrenom($userId, $lastname, $firstname);
        $this->utilisateurSQL->setEmail($userId, $email);
        $this->notificationDigestSQL->updateEmail($oldInfo['email'], $email);
        $this->utilisateurSQL->setLogin($userId, $login);
        $this->utilisateurSQL->setColBase($userId, $entityId);

        $roles = $this->roleUtilisateur->getRole($userId);
        if (!$roles) {
            $this->roleUtilisateur->addRole($userId, RoleUtilisateur::AUCUN_DROIT, $entityId);
        }

        $newInfo = $this->utilisateurSQL->getInfo($userId);

        $infoToRetrieve = ['email', 'login', 'nom', 'prenom'];
        $infoChanged = [];
        foreach ($infoToRetrieve as $key) {
            if ($oldInfo[$key] !== $newInfo[$key]) {
                $infoChanged[] = "$key : {$oldInfo[$key]} -> {$newInfo[$key]}";
            }
        }
        $info = implode('; ', $infoChanged);

        $this->journal->add(
            Journal::MODIFICATION_UTILISATEUR,
            $entityId,
            0,
            Journal::ACTION_MODIFFIE,
            "Modification de l'utilisateur $login ($userId) : $info"
        );

        return $userId;
    }
}
