<?php

class UtilisateurNewEmailSQLTest extends PastellTestCase
{
    private const EMAIL = 'newmail@example.invalid';
    private const PASSWORD = 'abc1234';

    /** @var  UtilisateurNewEmailSQL */
    private $utilisateurNewEmailSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utilisateurNewEmailSQL = new UtilisateurNewEmailSQL($this->getSQLQuery(), new PasswordGenerator());
    }

    private function add()
    {
        return $this->utilisateurNewEmailSQL->add(1, "newmail@pastell.com");
    }

    private function listTable()
    {
        return $this->getSQLQuery()->query("SELECT * FROM utilisateur_new_email");
    }

    private function insert(string $date): void
    {
        $this->utilisateurNewEmailSQL->insert(1, self::EMAIL, self::PASSWORD, $date);
    }

    public function testAdd()
    {
        $this->add();
        $result = $this->listTable();
        $this->assertEquals("newmail@pastell.com", $result[0]['email']);
    }

    public function testConfirm()
    {
        $password = $this->add();
        $this->assertEquals("newmail@pastell.com", $this->utilisateurNewEmailSQL->confirm($password)['email']);
    }

    public function testDelete(): void
    {
        $this->insert(date(Date::DATE_ISO));
        $this->utilisateurNewEmailSQL->delete(1);
        self::assertEmpty($this->listTable());
    }

    public function testInsert(): void
    {
        $date = '2026-11-18 10:00:00';
        $this->insert($date);

        $result = $this->listTable();
        self::assertSame(self::EMAIL, $result[0]['email']);
        self::assertSame(self::PASSWORD, $result[0]['password']);
        self::assertSame($date, $result[0]['date']);
    }

    public function testGetByPasswordSince(): void
    {
        $this->insert('2026-11-18 10:00:00');

        $request_info = $this->utilisateurNewEmailSQL->getByPasswordSince(self::PASSWORD, '2026-11-18 09:00:00');
        self::assertSame(self::EMAIL, $request_info['email']);
    }

    public function testGetExpiredRequest(): void
    {
        $this->insert('2026-11-18 10:00:00');

        self::assertNull($this->utilisateurNewEmailSQL->getByPasswordSince(self::PASSWORD, '2026-11-18 11:00:00'));
    }

    public function testGetByUnknownPassword(): void
    {
        $this->insert('2026-11-18 10:00:00');

        self::assertNull($this->utilisateurNewEmailSQL->getByPasswordSince('unknown', '2026-11-18 09:00:00'));
    }
}
