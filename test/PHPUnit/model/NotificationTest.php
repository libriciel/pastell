<?php

declare(strict_types=1);

class NotificationTest extends PastellTestCase
{
    /**
     * @var Notification
     */
    private Notification $notification;
    private UtilisateurSQL $utilisateurSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notification = new Notification(self::getSQLQuery());
        $this->utilisateurSQL = new UtilisateurSQL(self::getSQLQuery());
    }

    public function testAdd(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $info = $this->notification->getAll(1);
        static::assertEquals('actes-generique', $info['1-actes-generique']['type']);
    }

    public function testAddTwoTimes(): void
    {
        static::assertCount(0, $this->notification->getAll(1));
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        static::assertCount(1, $this->notification->getAll(1));
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        static::assertCount(1, $this->notification->getAll(1));
    }

    public function testHasDailyDigest(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', true);
        static::assertSame(1, $this->notification->hasDailyDigest(1, 1, 'actes-generique'));
    }

    public function testGetNotificationActionList(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $info = $this->notification->getNotificationActionList(1, 1, 'actes-generique', [['id' => 'send-tdt']]);
        static::assertTrue($info[0]['checked']);
    }

    public function testGetInfo(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        $id_n = $all_info[0]['id_n'];
        $info = $this->notification->getInfo($id_n);
        static::assertSame($all_info[0]['action'], $info['action']);
    }

    public function testRemove(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        $id_n = $all_info[0]['id_n'];
        $this->notification->remove($id_n);
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        static::assertEmpty($all_info);
    }

    public function testGetMail(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $info = $this->notification->getMail(1, 'actes-generique', 'send-tdt');
        static::assertSame(['eric@sigmalis.com'], $info);
    }

    public function testRemoveAll(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $this->notification->removeAll(1, 1, 'actes-generique');
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        static::assertEmpty($all_info);
    }

    public function testToogleDailyDigest(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $this->notification->toogleDailyDigest(1, 1, 'actes-generique');
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        static::assertSame(1, $all_info[0]['daily_digest']);
    }

    public function testDisabledUser(): void
    {
        $this->notification->add(1, 1, 'actes-generique', 'send-tdt', false);
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        static::assertCount(1, $all_info);
        $this->utilisateurSQL->disable(1);
        $all_info = $this->notification->getAllInfo(1, 'actes-generique', 'send-tdt');
        static::assertEmpty($all_info);
    }
}
