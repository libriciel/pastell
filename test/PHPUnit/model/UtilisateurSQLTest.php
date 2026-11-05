<?php

declare(strict_types=1);

class UtilisateurSQLTest extends PastellTestCase
{
    private UtilisateurSQL $utilisateurSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
    }

    public function testEnable(): void
    {
        $this->utilisateurSQL->enable(1);
        self::assertTrue($this->utilisateurSQL->isEnabled(1));
    }

    public function testDisable(): void
    {
        $this->utilisateurSQL->disable(1);
        self::assertFalse($this->utilisateurSQL->isEnabled(1));
    }

    public function testGetAllBase(): void
    {
        self::assertEqualsCanonicalizing([1, 2], array_column($this->utilisateurSQL->getAllByBaseEntite(0), 'id_u'));
    }

    public function testCountDeBase(): void
    {
        self::assertSame(2, $this->utilisateurSQL->countByBaseEntite(0));
    }

    public function testCountUnknownRole(): void
    {
        self::assertSame(0, $this->utilisateurSQL->countByEntite(0, true, 'does not exist', 'eric'));
    }

    public function testVerifPassword(): void
    {
        $this->utilisateurSQL->reinitPassword(1, 'foo');
        self::assertSame(1, $this->utilisateurSQL->getIdByVerifPassword('foo'));
    }

    public function testVerifPasswordExpired(): void
    {
        $this->utilisateurSQL->reinitPassword(1, 'foo');
        $this->getSQLQuery()->query(
            'UPDATE utilisateur SET password_reset_requested_at = ? where id_u = ?',
            date('c', strtotime('-30 minutes')),
            1
        );
        self::assertNull($this->utilisateurSQL->getIdByVerifPassword('foo', 29 * 60));
        self::assertSame(1, $this->utilisateurSQL->getIdByVerifPassword('foo', 31 * 60));
    }
}
