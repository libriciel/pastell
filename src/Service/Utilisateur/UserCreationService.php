<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use ConflictException;
use Exception;
use Journal;
use Pastell\Service\TokenGenerator;
use Pastell\Validator\UserValidator;
use RoleUtilisateur;
use UnrecoverableException;
use UtilisateurSQL;

final class UserCreationService
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly TokenGenerator $tokenGenerator,
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly Journal $journal,
        private readonly UserValidator $userValidator,
    ) {
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function create(
        string $login,
        string $email,
        string $firstname,
        string $lastname,
        int $entityId = 0,
        ?string $password = null,
    ): int {
        if ($password === null) {
            $password = $this->tokenGenerator->generate();
        }
        $this->userValidator->validateNewUser(
            $login,
            $email,
            $firstname,
            $lastname,
            $password,
            $entityId,
        );

        $emailPasswordValidation = $this->tokenGenerator->generate();

        $userId = $this->utilisateurSQL->create($login, $password, $email, $emailPasswordValidation);

        $this->utilisateurSQL->validMailAuto($userId);
        $this->utilisateurSQL->setNomPrenom($userId, $lastname, $firstname);
        $this->utilisateurSQL->setEmail($userId, $email);
        $this->utilisateurSQL->setLogin($userId, $login);
        $this->utilisateurSQL->setColBase($userId, $entityId);

        $this->roleUtilisateur->addRole($userId, RoleUtilisateur::AUCUN_DROIT, $entityId);

        $info = \implode('; ', [
            'prenom : ' . $firstname,
            'nom : ' . $lastname,
        ]);

        $this->journal->add(
            Journal::MODIFICATION_UTILISATEUR,
            $entityId,
            0,
            Journal::ACTION_CREATED,
            "Création de l'utilisateur $login ($userId) : $info"
        );

        return $userId;
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function createAPI(
        string $login,
        int $id_e,
        string $lastname,
        string $firstname,
    ): int {
        $password = $this->tokenGenerator->generate();

        $this->userValidator->validateNewAPIUser($login, $lastname, $firstname, $id_e);
        $emailPasswordValidation = $this->tokenGenerator->generate();
        $userId = $this->utilisateurSQL->create($login, $password, '', $emailPasswordValidation);
        $this->utilisateurSQL->setIsAPI($userId, true);
        $this->utilisateurSQL->setColBase($userId, $id_e);
        $this->utilisateurSQL->setLogin($userId, $login);
        $this->utilisateurSQL->setNomPrenom($userId, $firstname, $lastname);
        $this->roleUtilisateur->addRole($userId, RoleUtilisateur::AUCUN_DROIT, $id_e);

        $info = \implode('; ', [
            'prenom : ' . $firstname,
            'nom : ' . $lastname,
        ]);

        $this->journal->add(
            Journal::MODIFICATION_UTILISATEUR,
            $id_e,
            0,
            Journal::ACTION_CREATED,
            "Création de l'utilisateur API $login ($userId) : $info"
        );

        return $userId;
    }
}
