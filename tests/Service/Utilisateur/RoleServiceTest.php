<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;
use Pastell\Service\Notification\NotificationService;
use Pastell\Service\Utilisateur\RoleService;
use PastellTestCase;
use RoleSQL;

class RoleServiceTest extends PastellTestCase
{
    private RoleService $roleService;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = $this->getObjectInstancier()->getInstance(RoleService::class);
        $this->notificationService = $this->getObjectInstancier()->getInstance(NotificationService::class);
    }

    public function testUpdateDroitRevokes(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);

        $this->roleService->updateDroit('admin', ['entite:lecture']);

        static::assertEmpty($this->notificationService->getSubscriptionsByUser(1));
    }

    public function testIgnoresUnknownDroit(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->deleteAllDroit('admin');
        $roleSQL->insertDroit('admin', 'entite:lecture');
        $roleSQL->insertDroit('admin', 'flux-inexistant:lecture');
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);

        $this->roleService->updateDroit('admin', ['entite:lecture']);

        static::assertCount(1, $this->notificationService->getSubscriptionsByUser(1));
    }

    public function testSaveRoleInsert(): void
    {
        $this->roleService->saveRole('role1', 'Rôle 1');

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        self::assertSame('Rôle 1', $roleSQL->getInfo('role1')['libelle']);
        self::assertSame([], $roleSQL->getDroitList('role1'));
    }

    public function testSaveRoleUpdate(): void
    {
        $this->roleService->saveRole('admin', 'Super administrateur');

        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        self::assertSame('Super administrateur', $roleSQL->getInfo('admin')['libelle']);
        self::assertNotEmpty($roleSQL->getDroitList('admin'));
    }

    public function testCreateRoleDefaultDroits(): void
    {
        $this->roleService->createRole('role1', 'Rôle 1');

        self::assertSame(
            [
                DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE),
                DroitService::getDroitFor(DroitService::DROIT_JOURNAL, DroitType::LECTURE),
            ],
            $this->getObjectInstancier()->getInstance(RoleSQL::class)->getDroitList('role1')
        );
    }

    public function testGetDroitMap(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->insertDroit('autre', 'entite:lecture');
        $roleSQL->insertDroit('autre', 'flux-inexistant:lecture');

        self::assertSame(
            [
                'entite:lecture' => true,
                'entite:edition' => false,
                'flux-inexistant:lecture' => true,
            ],
            $this->roleService->getDroitMap(['entite:lecture', 'entite:edition'], 'autre')
        );
    }

    public function testGetUnrestrictedDroitList(): void
    {
        $roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
        $roleSQL->insertDroit('autre', 'entite:lecture');
        $roleSQL->insertDroit('autre', 'test:lecture');
        $this->setListPack(['suppl_test' => false]);

        self::assertSame(['entite:lecture'], $this->roleService->getUnrestrictedDroitList('autre'));
    }

    public function testAddDroitTwice(): void
    {
        $this->roleService->addDroit('autre', 'entite:lecture');
        $this->roleService->addDroit('autre', 'entite:lecture');

        self::assertSame(
            ['entite:lecture'],
            $this->getObjectInstancier()->getInstance(RoleSQL::class)->getDroitList('autre')
        );
    }
}
