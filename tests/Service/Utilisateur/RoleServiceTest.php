<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

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
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->updateDroit('admin', ['entite:lecture', 'flux-inexistant:lecture']);
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);

        $this->roleService->updateDroit('admin', ['entite:lecture']);

        static::assertCount(1, $this->notificationService->getSubscriptionsByUser(1));
    }
}
