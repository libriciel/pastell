<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Date;
use DocumentSQL;
use Pastell\Service\Utilisateur\UserCreationService;
use Pastell\Service\Utilisateur\UserEmailChangeService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use PastellTestCase;
use RoleSQL;
use UtilisateurNewEmailSQL;

final class UserEmailChangeServiceTest extends PastellTestCase
{
    private const EMAIL = 'newmail@example.invalid';
    private const PASSWORD = 'abc1234';

    private function getUserEmailChangeService(): UserEmailChangeService
    {
        return $this->getObjectInstancier()->getInstance(UserEmailChangeService::class);
    }

    private function createUserAllowedToChangeEmail(): int
    {
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->updateDroit(
            'change_email_role',
            ['changement-email:edition']
        );
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)->create(
            'change_email_login',
            'user@example.invalid',
            'user',
            'user',
            self::ID_E_COL,
        );
        $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class)->addRole(
            $id_u,
            'change_email_role',
            self::ID_E_COL
        );
        return $id_u;
    }

    public function testCreateRequest(): void
    {
        $password = $this->getUserEmailChangeService()->createRequest(1, self::EMAIL);

        $request_info = $this->getUserEmailChangeService()->getValidRequest($password);
        self::assertSame(self::EMAIL, $request_info['email']);
        self::assertSame(1, (int)$request_info['id_u']);
    }

    public function testCreateRequestReplacesPrevious(): void
    {
        $old_password = $this->getUserEmailChangeService()->createRequest(1, 'old@example.invalid');
        $password = $this->getUserEmailChangeService()->createRequest(1, self::EMAIL);

        self::assertNull($this->getUserEmailChangeService()->getValidRequest($old_password));
        self::assertSame(self::EMAIL, $this->getUserEmailChangeService()->getValidRequest($password)['email']);
    }

    public function testGetExpiredRequest(): void
    {
        $this->getObjectInstancier()->getInstance(UtilisateurNewEmailSQL::class)->insert(
            1,
            self::EMAIL,
            self::PASSWORD,
            date(Date::DATE_ISO, strtotime('-2 days'))
        );

        self::assertNull($this->getUserEmailChangeService()->getValidRequest(self::PASSWORD));
    }

    public function testDeleteRequest(): void
    {
        $password = $this->getUserEmailChangeService()->createRequest(1, self::EMAIL);
        $this->getUserEmailChangeService()->deleteRequest(1);

        self::assertNull($this->getUserEmailChangeService()->getValidRequest($password));
    }

    public function testGetUnknownRequest(): void
    {
        self::assertNull($this->getUserEmailChangeService()->getValidRequest('unknown'));
    }

    public function testConfirmRequest(): void
    {
        $id_u = $this->createUserAllowedToChangeEmail();
        $password = $this->getUserEmailChangeService()->createRequest($id_u, self::EMAIL);

        self::assertTrue($this->getUserEmailChangeService()->confirmRequest($password));
        self::assertNull($this->getUserEmailChangeService()->getValidRequest($password));

        $documentSQL = $this->getObjectInstancier()->getInstance(DocumentSQL::class);
        $id_d_list = $documentSQL->getAllIdByType('changement-email');
        self::assertCount(1, $id_d_list);
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d_list[0]['id_d']);
        self::assertSame(self::EMAIL, $donneesFormulaire->get('email_demande'));
    }

    public function testConfirmUnknownRequest(): void
    {
        self::assertFalse($this->getUserEmailChangeService()->confirmRequest('unknown'));
    }
}
