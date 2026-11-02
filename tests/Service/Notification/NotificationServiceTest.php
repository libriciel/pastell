<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Notification;

use NotificationSubscription;
use Pastell\Service\Notification\NotificationService;
use Pastell\Service\Utilisateur\UtilisateurRoleService;
use PastellTestCase;
use RoleSQL;
use UtilisateurSQL;

class NotificationServiceTest extends PastellTestCase
{
    private NotificationService $notificationService;
    private UtilisateurSQL $utilisateurSQL;
    private RoleSQL $roleSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = $this->getObjectInstancier()->getInstance(NotificationService::class);
        $this->utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $this->roleSQL = $this->getObjectInstancier()->getInstance(RoleSQL::class);
    }

    public function testSubscribe(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $subscriptions = $this->notificationService->getSubscriptionsByUser(1);
        static::assertSame('actes-generique', $subscriptions['1-actes-generique']['type']);
    }

    public function testSubscribeTwice(): void
    {
        static::assertCount(0, $this->notificationService->getSubscriptionsByUser(1));
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        static::assertCount(1, $this->notificationService->getSubscriptionsByUser(1));
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        static::assertCount(1, $this->notificationService->getSubscriptionsByUser(1));
    }

    public function testHasDailyDigest(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', true);
        static::assertTrue($this->notificationService->hasDailyDigest(1, 1, 'actes-generique'));
    }

    public function testSetSubscriptionState(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $actionList = $this->notificationService
            ->setSubscriptionStateOnActionList(1, 1, 'actes-generique', [['id' => 'send-tdt']]);
        static::assertTrue($actionList[0]['checked']);
    }

    public function testGetSubscription(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $recipients = $this->notificationService->getRecipients(1, 'actes-generique', 'send-tdt');
        $subscription = $this->notificationService->getSubscription($recipients[0]->id_n);
        static::assertSame('send-tdt', $subscription->action);
    }

    public function testUnsubscribeAll(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $this->notificationService->unsubscribeAll(1, 1, 'actes-generique');
        static::assertEmpty($this->notificationService->getRecipients(1, 'actes-generique', 'send-tdt'));
    }

    public function testToggleDailyDigest(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $this->notificationService->toggleDailyDigest(1, 1, 'actes-generique');
        static::assertTrue($this->notificationService->hasDailyDigest(1, 1, 'actes-generique'));
    }

    public function testGetRecipientsIgnoresDisabled(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        static::assertCount(1, $this->notificationService->getRecipients(1, 'actes-generique', 'send-tdt'));
        $this->utilisateurSQL->disable(1);
        static::assertEmpty($this->notificationService->getRecipients(1, 'actes-generique', 'send-tdt'));
    }

    public function testRevokeForRole(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $this->notificationService->subscribe(1, 1, 'helios-generique', 'send-tdt', false);
        $this->roleSQL->updateDroit('admin', ['entite:lecture', 'actes-generique:lecture']);

        $this->notificationService->revokeInvalidForRole('admin');

        static::assertSame(
            ['1-actes-generique'],
            array_keys($this->notificationService->getSubscriptionsByUser(1))
        );
    }

    public function testRevokeWithoutEntiteLecture(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $this->roleSQL->updateDroit('admin', ['actes-generique:lecture']);

        $this->notificationService->revokeInvalid(1);

        static::assertEmpty($this->notificationService->getSubscriptionsByUser(1));
    }

    public function testRevokeWithEntiteEdition(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $this->roleSQL->updateDroit('admin', ['entite:edition']);

        $this->notificationService->revokeInvalid(1);

        static::assertEmpty($this->notificationService->getSubscriptionsByUser(1));
    }

    public function testRevokeKeepsAllType(): void
    {
        $this->notificationService->subscribe(1, 1, NotificationSubscription::ALL_TYPE, 'send-tdt', false);
        $this->roleSQL->updateDroit('admin', ['entite:lecture']);

        $this->notificationService->revokeInvalid(1);

        static::assertCount(1, $this->notificationService->getSubscriptionsByUser(1));
    }

    public function testRevokeAllType(): void
    {
        $this->notificationService->subscribe(1, 1, NotificationSubscription::ALL_TYPE, 'send-tdt', false);
        $this->roleSQL->updateDroit('admin', []);

        $this->notificationService->revokeInvalid(1);

        static::assertEmpty($this->notificationService->getSubscriptionsByUser(1));
    }

    public function testRevokeIgnoresDisabledPack(): void
    {
        $this->roleSQL->updateDroit('admin', ['entite:lecture', 'facture-cpp:lecture']);
        $this->notificationService->subscribe(1, 1, 'facture-cpp', 'send-tdt', false);
        $this->setListPack(['pack_chorus_pro' => false]);

        $this->notificationService->revokeInvalid(1);

        static::assertCount(1, $this->notificationService->getSubscriptionsByUser(1));
    }

    public function testRemoveRoleRevokes(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);

        $this->getObjectInstancier()->getInstance(UtilisateurRoleService::class)->removeRole(1, 'admin', 0);

        static::assertEmpty($this->notificationService->getSubscriptionsByUser(1));
    }
}
