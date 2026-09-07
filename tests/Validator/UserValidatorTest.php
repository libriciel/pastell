<?php

declare(strict_types=1);

namespace Pastell\Tests\Validator;

use ConflictException;
use Exception;
use Pastell\Service\TokenGenerator;
use Pastell\Validator\UserValidator;
use PastellTestCase;
use UnrecoverableException;

class UserValidatorTest extends PastellTestCase
{
    public function userValidator(): UserValidator
    {
        return $this->getObjectInstancier()->getInstance(UserValidator::class);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testValidateNewUser(): void
    {
        static::assertTrue(
            $this->userValidator()->validateNewUser(
                'login',
                'mail@example.org',
                'firstname',
                'lastname',
                (new TokenGenerator())->generate(),
                0,
            )
        );
    }

    /**
     * @throws Exception
     */
    public static function newUserProvider(): \Generator
    {
        yield 'empty login' => [
            '',
            'mail',
            'firstname',
            'lastname',
            '',
            0,
            'Le login est obligatoire'
        ];
        yield 'invalid mail' => [
            'login',
            'mail',
            'firstname',
            'lastname',
            '',
            0,
            'Votre adresse email ne semble pas valide'
        ];
        yield 'empty firstname' => [
            'login',
            'mail',
            '',
            'lastname',
            '',
            0,
            'Le prénom est obligatoire'
        ];
        yield 'empty lastname' => [
            'login',
            'mail',
            'firstname',
            '',
            '',
            0,
            'Le nom est obligatoire'
        ];
        yield 'not existing entity' => [
            'login',
            'mail@example.org',
            'firstname',
            'lastname',
            '',
            500,
            "L'entité 500 n'existe pas"
        ];
        yield 'invalid password' => [
            'login',
            'mail@example.org',
            'firstname',
            'lastname',
            '',
            0,
            "Le mot de passe n'est pas assez fort. (trop court ou pas assez de caractères différents)"
        ];
        yield 'existing user' => [
            'admin',
            'mail@example.org',
            'firstname',
            'lastname',
            (new TokenGenerator())->generate(),
            0,
            'Un utilisateur avec le même login existe déjà.'
        ];
    }

    /**
     * @dataProvider newUserProvider
     * @throws ConflictException
     * @throws UnrecoverableException
     */
    public function testValidateErrors(
        string $login,
        string $email,
        string $firstname,
        string $lastname,
        string $password,
        int $entityId,
        string $expectedMessage
    ): void {
        $this->expectExceptionMessage($expectedMessage);
        $this->userValidator()->validateNewUser(
            $login,
            $email,
            $firstname,
            $lastname,
            $password,
            $entityId,
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    public function testValidateExistingUser(): void
    {
        static::assertTrue(
            $this->userValidator()->validateExistingUser(
                1,
                'admin',
                'mail@example.org',
                'firstname',
                'lastname',
                0,
                null,
            )
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function testValidateOnExistingLogin(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Un utilisateur avec le même login existe déjà');

        $this->userValidator()->validateExistingUser(
            2,
            'admin',
            'mail@example.org',
            'firstname',
            'lastname',
            0,
            null,
        );
    }
}
