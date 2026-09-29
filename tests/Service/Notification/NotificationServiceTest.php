<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Notification;

use ConflictException;
use NotFoundException;
use Notification;
use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Notification\NotificationService;
use Pastell\Service\Utilisateur\UserCreationService;
use PastellTestCase;
use RoleSQL;
use RoleUtilisateur;
use UnrecoverableException;

class NotificationServiceTest extends PastellTestCase
{
    private const TYPE = 'actes-generique';

    private function getService(): NotificationService
    {
        return $this->getObjectInstancier()->getInstance(NotificationService::class);
    }

    private function getNotification(): Notification
    {
        return $this->getObjectInstancier()->getInstance(Notification::class);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     */
    private function createUser(): int
    {
        return $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('lecteur', 'lecteur@example.org', 'lecteur', 'lecteur', 1);
    }

    private function grantRole(int $id_u, array $droits, int $id_e): void
    {
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->updateDroit('lecteur', $droits);
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole($id_u, 'lecteur', $id_e);
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testRemovesWhenNoAccess(): void
    {
        $id_u = $this->createUser();
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u);

        static::assertEmpty($this->getNotification()->getAll($id_u));
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testKeepsWithTypeRight(): void
    {
        $id_u = $this->createUser();
        $this->grantRole($id_u, [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            DroitService::getDroitFor(self::TYPE, DroitType::LECTURE),
        ], 1);
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u);

        static::assertCount(1, $this->getNotification()->getAll($id_u));
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testRemovesWithoutTypeRight(): void
    {
        $id_u = $this->createUser();
        $this->grantRole($id_u, [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
        ], 1);
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u);

        static::assertEmpty($this->getNotification()->getAll($id_u));
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testKeepsWithEditionRight(): void
    {
        $id_u = $this->createUser();
        $this->grantRole($id_u, [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::EDITION),
            DroitService::getDroitFor(self::TYPE, DroitType::EDITION),
        ], 1);
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u);

        static::assertCount(1, $this->getNotification()->getAll($id_u));
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testCascadesToChild(): void
    {
        $id_u = $this->createUser();
        $this->grantRole($id_u, [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            DroitService::getDroitFor(self::TYPE, DroitType::LECTURE),
        ], 1);
        $this->getNotification()->add($id_u, 2, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u);

        static::assertCount(1, $this->getNotification()->getAll($id_u));
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testKeepsViaRootRole(): void
    {
        $id_u = $this->createUser();
        $this->grantRole($id_u, [
            DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
            DroitService::getDroitFor(self::TYPE, DroitType::LECTURE),
        ], 0);
        $this->getNotification()->add($id_u, 1, self::TYPE, Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u);

        static::assertCount(1, $this->getNotification()->getAll($id_u));
    }
}
