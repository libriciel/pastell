<?php

declare(strict_types=1);

class EntiteMfaObligationSQLTest extends PastellTestCase
{
    private function getEntiteMfaObligationSQL(): EntiteMfaObligationSQL
    {
        return $this->getObjectInstancier()->getInstance(EntiteMfaObligationSQL::class);
    }

    public function testEnableAndDisable(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        self::assertFalse($sql->isDirectlySet(self::ID_E_COL));

        $sql->enable(self::ID_E_COL, self::ID_U_ADMIN);
        self::assertTrue($sql->isDirectlySet(self::ID_E_COL));

        $sql->disable(self::ID_E_COL);
        self::assertFalse($sql->isDirectlySet(self::ID_E_COL));
    }

    public function testEnableIsIdempotent(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        $sql->enable(self::ID_E_COL, self::ID_U_ADMIN);
        $sql->enable(self::ID_E_COL, self::ID_U_ADMIN);
        self::assertTrue($sql->isDirectlySet(self::ID_E_COL));
    }

    public function testAppliesToDescendant(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        $sql->enable(self::ID_E_COL);
        self::assertTrue($sql->appliesTo(self::ID_E_SERVICE));
    }

    public function testInheritedAncestorIsMother(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        $sql->enable(self::ID_E_COL);

        self::assertSame(self::ID_E_COL, $sql->getEnforcingAncestor(self::ID_E_SERVICE));
        self::assertFalse($sql->isDirectlySet(self::ID_E_SERVICE));
    }

    public function testDirectTakesPrecedence(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        $sql->enable(self::ID_E_SERVICE);
        self::assertSame(self::ID_E_SERVICE, $sql->getEnforcingAncestor(self::ID_E_SERVICE));
    }

    public function testNoObligation(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        self::assertNull($sql->getEnforcingAncestor(self::ID_E_SERVICE));
        self::assertFalse($sql->appliesTo(self::ID_E_SERVICE));
    }

    public function testProperAncestorExcludesSelf(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        $sql->enable(self::ID_E_SERVICE);
        self::assertNull($sql->getEnforcingProperAncestor(self::ID_E_SERVICE));
    }

    public function testProperAncestorWithDirect(): void
    {
        $sql = $this->getEntiteMfaObligationSQL();
        $sql->enable(self::ID_E_SERVICE);
        $sql->enable(self::ID_E_COL);
        self::assertSame(self::ID_E_COL, $sql->getEnforcingProperAncestor(self::ID_E_SERVICE));
    }
}
