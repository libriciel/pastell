<?php

declare(strict_types=1);

class UtilisateurSQLTest extends PastellTestCase
{
    public function testEnable(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $utilisateurSQL->enable(1);
        self::assertTrue($utilisateurSQL->isEnabled(1));
    }

    public function testDisable(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $utilisateurSQL->disable(1);
        self::assertFalse($utilisateurSQL->isEnabled(1));
    }

    public function testGetAllBase(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertEqualsCanonicalizing([1, 2], array_column($utilisateurSQL->getAllByBaseEntite(0), 'id_u'));
    }

    public function testCountDeBase(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertSame(2, $utilisateurSQL->countByEntiteDeBase(0));
    }

    public function testCountUnknownRole(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        self::assertSame(0, $utilisateurSQL->countByEntite(0, true, 'does not exist', 'eric'));
    }

    public function testVerifPassword(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $utilisateurSQL->reinitPassword(1, 'foo');
        self::assertSame(1, $utilisateurSQL->getIdByVerifPassword('foo'));
    }

    public function testVerifPasswordExpired(): void
    {
        $utilisateurSQL = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $utilisateurSQL->reinitPassword(1, 'foo');
        $this->getSQLQuery()->query(
            'UPDATE utilisateur SET password_reset_requested_at = ? where id_u = ?',
            date('c', strtotime('-30 minutes')),
            1
        );
        self::assertNull($utilisateurSQL->getIdByVerifPassword('foo', 29 * 60));
        self::assertSame(1, $utilisateurSQL->getIdByVerifPassword('foo', 31 * 60));
    }
}
