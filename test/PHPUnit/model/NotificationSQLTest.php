<?php

declare(strict_types=1);

class NotificationSQLTest extends PastellTestCase
{
    private NotificationSQL $notificationSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationSQL = new NotificationSQL(self::getSQLQuery());
    }

    public function testInsertAndCount(): void
    {
        $subscription = new NotificationSubscription(1, 1, 'actes-generique', 'send-tdt');
        static::assertSame(0, $this->notificationSQL->countSubscription($subscription));
        $this->notificationSQL->insert($subscription);
        static::assertSame(1, $this->notificationSQL->countSubscription($subscription));
    }

    public function testGetByIdReturnsObject(): void
    {
        $this->notificationSQL->insert(new NotificationSubscription(1, 1, 'actes-generique', 'send-tdt'));
        $id_n = $this->notificationSQL->getByUser(1)[0]->id_n;
        $subscription = $this->notificationSQL->getById($id_n);
        static::assertSame('send-tdt', $subscription->action);
        static::assertSame(1, $subscription->id_u);
    }

    public function testGetByIdUnknown(): void
    {
        static::assertNull($this->notificationSQL->getById(9999));
    }

    public function testMatchingOnlyEnabled(): void
    {
        $this->notificationSQL->insert(new NotificationSubscription(1, 1, 'actes-generique', 'send-tdt'));
        static::assertCount(1, $this->notificationSQL->getMatchingSubscriptions(1, 'actes-generique', 'send-tdt', true));
        $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->disable(1);
        static::assertCount(1, $this->notificationSQL->getMatchingSubscriptions(1, 'actes-generique', 'send-tdt', false));
        static::assertCount(0, $this->notificationSQL->getMatchingSubscriptions(1, 'actes-generique', 'send-tdt', true));
    }

    public function testFromArrayRoundTrip(): void
    {
        $subscription = NotificationSubscription::fromArray([
            'id_n' => '5',
            'id_u' => '1',
            'id_e' => '2',
            'type' => 'actes-generique',
            'action' => 'send-tdt',
            'daily_digest' => '1',
        ]);
        static::assertSame(5, $subscription->id_n);
        static::assertTrue($subscription->daily_digest);
        static::assertSame(
            ['id_n' => 5, 'id_u' => 1, 'id_e' => 2, 'type' => 'actes-generique', 'action' => 'send-tdt', 'daily_digest' => 1],
            $subscription->toArray(),
        );
    }
}
