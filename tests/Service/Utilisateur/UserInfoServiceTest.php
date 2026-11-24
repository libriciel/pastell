<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Exception;
use NotFoundException;
use Pastell\Service\Utilisateur\UserInfoService;
use PastellTestCase;

final class UserInfoServiceTest extends PastellTestCase
{
    private function getUserInfoService(): UserInfoService
    {
        return $this->getObjectInstancier()->getInstance(UserInfoService::class);
    }

    /**
     * @throws Exception
     */
    public function testGetUserFromId(): void
    {
        self::assertSame('admin', $this->getUserInfoService()->getUserFromData(['id_u' => 1])['login']);
    }

    /**
     * @throws Exception
     */
    public function testGetUserFromLogin(): void
    {
        self::assertSame(1, (int)$this->getUserInfoService()->getUserFromData(['login' => 'admin'])['id_u']);
    }

    /**
     * @throws Exception
     */
    public function testGetUserUnknownId(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getUserInfoService()->getUserFromData(['id_u' => 42]);
    }

    /**
     * @throws Exception
     */
    public function testGetUserUnknownLogin(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getUserInfoService()->getUserFromData(['login' => 'unknown']);
    }

    /**
     * @throws Exception
     */
    public function testGetUserWithoutData(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Aucun paramètre permettant la recherche de l'utilisateur n'a été renseigné");
        $this->getUserInfoService()->getUserFromData([]);
    }
}
