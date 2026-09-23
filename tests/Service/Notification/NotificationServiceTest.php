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

    private function grantEntiteAccess(int $id_u): void
    {
        $this->getObjectInstancier()->getInstance(RoleSQL::class)->updateDroit(
            'lecteur',
            [DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE)],
        );
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)->addRole($id_u, 'lecteur', 1);
    }

    /**
     * @throws ConflictException
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function testRemovesWhenNoAccess(): void
    {
        $id_u = $this->createUser();
        $this->getNotification()->add($id_u, 1, 'actes-generique', Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u, 1);

        static::assertEmpty($this->getNotification()->getAll($id_u));
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testKeepsWhenAccess(): void
    {
        $id_u = $this->createUser();
        $this->grantEntiteAccess($id_u);
        $this->getNotification()->add($id_u, 1, 'actes-generique', Notification::ALL_TYPE, false);

        $this->getService()->purgeIfNoAccess($id_u, 1);

        static::assertCount(1, $this->getNotification()->getAll($id_u));
    }

    public function testSubscribe(): void
    {
        $this->getService()->subscribe(1, 1, 'actes-generique', 0);

        static::assertCount(1, $this->getService()->getAllForUser(1));
    }

    public function testSetActions(): void
    {
        $this->getService()->setActions(1, 1, 'actes-generique', ['send-tdt'], false, 0);

        $list = $this->getService()->getActionList(1, 1, 'actes-generique', [['id' => 'send-tdt']]);
        static::assertTrue($list[0]['checked']);
    }

    public function testSetActionsAllChecked(): void
    {
        $this->getService()->setActions(1, 1, 'actes-generique', ['send-tdt'], true, 1);

        static::assertCount(1, $this->getService()->getAllForUser(1));
        static::assertSame(1, $this->getService()->hasDailyDigest(1, 1, 'actes-generique'));
    }

    public function testSetActionsEmpty(): void
    {
        $this->getService()->subscribe(1, 1, 'actes-generique', 0);

        $this->getService()->setActions(1, 1, 'actes-generique', [], false, 0);

        static::assertEmpty($this->getService()->getAllForUser(1));
    }

    public function testUnsubscribe(): void
    {
        $this->getService()->subscribe(1, 1, 'actes-generique', 0);

        $this->getService()->unsubscribe(1, 1, 'actes-generique');

        static::assertEmpty($this->getService()->getAllForUser(1));
    }

    public function testToggleDailyDigest(): void
    {
        $this->getService()->subscribe(1, 1, 'actes-generique', 0);

        $this->getService()->toggleDailyDigest(1, 1, 'actes-generique');

        static::assertSame(1, $this->getService()->hasDailyDigest(1, 1, 'actes-generique'));
    }
}
