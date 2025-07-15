<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use ConflictException;
use Journal;
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
    ): int {
        $this->userValidator->validateExistingUser(
            $userId,
            $login,
            $email,
            $firstname,
            $lastname,
            $entityId,
            $password,
        );

        $oldInfo = $this->utilisateurSQL->getInfo($userId);

        if ($password !== null) {
            $this->utilisateurSQL->setPassword($userId, $password);
        }
        $this->utilisateurSQL->validMailAuto($userId);
        $this->utilisateurSQL->setNomPrenom($userId, $lastname, $firstname);
        $this->utilisateurSQL->setEmail($userId, $email);
        $this->utilisateurSQL->setLogin($userId, $login);
        $this->utilisateurSQL->setColBase($userId, $entityId);

        $roles = $this->roleUtilisateur->getRole($userId);
        if (!$roles) {
            $this->roleUtilisateur->addRole($userId, RoleUtilisateur::AUCUN_DROIT, $entityId);
        }

        $newInfo = $this->utilisateurSQL->getInfo($userId);

        $infoToRetrieve = ['email', 'login', 'nom', 'prenom'];
        return $this->addToJournal($infoToRetrieve, $oldInfo, $newInfo, $entityId, $login, $userId);
    }

    /**
     * @throws ConflictException
     * @throws UnrecoverableException
     */
    public function updateAPI(
        int $userId,
        string $login,
        string $firstname,
        string $lastname,
        int $entityId = 0,
    ): int {
        $this->userValidator->validateExistingUserAPI(
            $userId,
            $login,
            $firstname,
            $lastname,
            $entityId,
        );

        $oldInfo = $this->utilisateurSQL->getInfo($userId);

        $this->utilisateurSQL->validMailAuto($userId);
        $this->utilisateurSQL->setNomPrenom($userId, $lastname, $firstname);
        $this->utilisateurSQL->setLogin($userId, $login);
        $this->utilisateurSQL->setColBase($userId, $entityId);

        if (!$this->roleUtilisateur->getRole($userId)) {
            $this->roleUtilisateur->addRole($userId, RoleUtilisateur::AUCUN_DROIT, $entityId);
        }

        $newInfo = $this->utilisateurSQL->getInfo($userId);

        $infoToRetrieve = ['login', 'nom', 'prenom'];
        return $this->addToJournal($infoToRetrieve, $oldInfo, $newInfo, $entityId, $login, $userId);
    }

    /**
     * @param array $infoToRetrieve
     * @param mixed $oldInfo
     * @param mixed $newInfo
     * @param int $entityId
     * @param string $login
     * @param int $userId
     * @return int
     */
    public function addToJournal(
        array $infoToRetrieve,
        mixed $oldInfo,
        mixed $newInfo,
        int $entityId,
        string $login,
        int $userId
    ): int {
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
