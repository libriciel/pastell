<?php

declare(strict_types=1);

namespace Pastell\Validator;

use ConflictException;
use EntiteSQL;
use Pastell\Service\PasswordEntropy;
use Pastell\Utilities\Certificate;
use UnrecoverableException;
use UtilisateurSQL;
use Pastell\Service\Entite\RootEntity;

final class UserValidator
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly EntiteSQL $entiteSQL,
        private readonly PasswordEntropy $passwordEntropy,
    ) {
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function validateNewUser(
        string $login,
        string $email,
        string $firstname,
        string $lastname,
        string $password,
        int $entityId,
        ?string $certificateContent
    ): bool {
        $this->validate($login, $lastname, $firstname, $email, $entityId, $certificateContent);
        $this->validatePassword($password);

        if ($this->utilisateurSQL->getIdFromLogin($login)) {
            throw new ConflictException('Un utilisateur avec le même login existe déjà.');
        }

        return true;
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function validateNewAPIUser(
        string $login,
        string $lastname,
        string $firstname,
        int $id_e,
    ): bool {
        $this->validateAPI($login, $lastname, $firstname, $id_e);
        if ($this->utilisateurSQL->getIdFromLogin($login)) {
            throw new ConflictException('Un utilisateur avec le même login existe déjà.');
        }

        return true;
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function validateExistingUser(
        int $userId,
        string $login,
        string $email,
        string $firstname,
        string $lastname,
        int $entityId,
        ?string $password,
        ?string $certificateContent
    ): bool {
        $this->validate($login, $lastname, $firstname, $email, $entityId, $certificateContent);
        if ($password !== null) {
            $this->validatePassword($password);
        }

        $userFromLogin = $this->utilisateurSQL->getIdFromLogin($login);
        if ($userFromLogin && $userFromLogin !== $userId) {
            throw new ConflictException('Un utilisateur avec le même login existe déjà.');
        }

        return true;
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function validateExistingUserAPI(
        int $userId,
        string $login,
        string $firstname,
        string $lastname,
        int $entityId,
    ): bool {
        $this->validateAPI($login, $lastname, $firstname, $entityId);
        $userFromLogin = $this->utilisateurSQL->getIdFromLogin($login);
        if ($userFromLogin && $userFromLogin !== $userId) {
            throw new ConflictException('Un utilisateur avec le même login existe déjà.');
        }
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    private function validate(
        string $login,
        string $lastname,
        string $firstname,
        string $email,
        int $entityId,
        ?string $certificateContent
    ): void {
        if ($login === '') {
            throw new UnrecoverableException('Le login est obligatoire');
        }
        if ($lastname === '') {
            throw new UnrecoverableException('Le nom est obligatoire');
        }
        if ($firstname === '') {
            throw new UnrecoverableException('Le prénom est obligatoire');
        }

        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new UnrecoverableException('Votre adresse email ne semble pas valide');
        }

        if ($entityId !== 0 && !$this->entiteSQL->exists($entityId)) {
            throw new UnrecoverableException("L'entité $entityId n'existe pas");
        }

        if ($certificateContent !== null) {
            $certificate = new Certificate($certificateContent);
            if (!$certificate->isValid()) {
                throw new UnrecoverableException('Le certificat ne semble pas être valide');
            }
        }
    }

    /**
     * @throws UnrecoverableException
     */
    public function validateAPI(
        string $login,
        string $lastname,
        string $firstname,
        int $id_e
    ): void {
        if ($login === '') {
            throw new UnrecoverableException('Le login est obligatoire');
        }
        if ($lastname === '') {
            throw new UnrecoverableException('Le nom est obligatoire');
        }
        if ($firstname === '') {
            throw new UnrecoverableException('Le prénom est obligatoire');
        }
        if ($id_e !== RootEntity::ID_E && !$this->entiteSQL->exists($id_e)) {
            throw new UnrecoverableException("L'entité $id_e n'existe pas");
        }
    }

    /**
     * @throws UnrecoverableException
     */
    private function validatePassword(string $password): void
    {
        if (!$this->passwordEntropy->isPasswordStrongEnough($password)) {
            throw new UnrecoverableException(
                "Le mot de passe n'est pas assez fort. (trop court ou pas assez de caractères différents)"
            );
        }
    }
}
