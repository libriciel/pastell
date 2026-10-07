<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Notification;

use Pastell\Service\Notification\NotificationService;
use PastellTestCase;
use UtilisateurSQL;

class NotificationServiceTest extends PastellTestCase
{
    private NotificationService $notificationService;
    private UtilisateurSQL $utilisateurSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = $this->getObjectInstancier()->getInstance(NotificationService::class);
        $this->utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
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

    public function testGetRecipientEmails(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        $emails = $this->notificationService->getRecipientEmails(1, 'actes-generique', 'send-tdt');
        static::assertSame(['eric@sigmalis.com'], $emails);
    }

    public function testGetRecipientsIgnoresDisabled(): void
    {
        $this->notificationService->subscribe(1, 1, 'actes-generique', 'send-tdt', false);
        static::assertCount(1, $this->notificationService->getRecipients(1, 'actes-generique', 'send-tdt'));
        $this->utilisateurSQL->disable(1);
        static::assertEmpty($this->notificationService->getRecipients(1, 'actes-generique', 'send-tdt'));
    }
}
