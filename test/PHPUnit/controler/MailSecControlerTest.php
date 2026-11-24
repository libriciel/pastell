<?php

declare(strict_types=1);

class MailSecControlerTest extends ControlerTestCase
{
    public function testAnnuaire(): void
    {
        /** @var MailSecControler $mailSecControler */
        $mailSecControler = $this->getControlerInstance(MailSecControler::class);
        \ob_start();
        $mailSecControler->annuaireAction();
        \ob_end_clean();
        $view_parameter = $mailSecControler->getViewParameter();
        $this->assertEquals(0, $view_parameter['id_e']);
    }

    /**
     * @throws NotFoundException
     */
    public function testAnnuaireImport(): void
    {
        /** @var MailSecControler $mailsecController */
        $mailsecController = $this->getControlerInstance(MailSecControler::class);
        \ob_start();
        $mailsecController->importAction();
        \ob_end_clean();
        $view_parameter = $mailsecController->getViewParameter();

        $this->assertSame(0, $view_parameter['id_e']);
        $this->assertSame('Annuaire global', $view_parameter['infoEntite']['denomination']);
    }

    private function getMailSecControler(): MailSecControler
    {
        return $this->getControlerInstance(MailSecControler::class);
    }

    private function getAnnuaireRoleSQL(): AnnuaireRoleSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireRoleSQL::class);
    }

    /**
     * @throws LastErrorException
     */
    public function testAddGroupeRole(): void
    {
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_e_owner' => self::ID_E_COL, 'role' => 'admin']);

        try {
            $mailSecControler->addGroupeRoleAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        self::assertSame(
            'admin - Bourg-en-Bresse',
            $this->getAnnuaireRoleSQL()->getAll(self::ID_E_COL)[0]['nom']
        );
    }

    /**
     * @throws LastMessageException
     */
    public function testAddGroupeRoleEmpty(): void
    {
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_e_owner' => self::ID_E_COL, 'role' => '']);

        try {
            $mailSecControler->addGroupeRoleAction();
            self::fail('LastErrorException expected');
        } catch (LastErrorException $lastErrorException) {
            self::assertStringContainsString('Le rôle est obligatoire', $lastErrorException->getMessage());
        }
        self::assertSame([], $this->getAnnuaireRoleSQL()->getAll(self::ID_E_COL));
    }

    /**
     * @throws LastMessageException
     */
    public function testAddGroupeRoleInvalid(): void
    {
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_e_owner' => self::ID_E_COL, 'role' => ['admin']]);

        try {
            $mailSecControler->addGroupeRoleAction();
            self::fail('LastErrorException expected');
        } catch (LastErrorException $lastErrorException) {
            self::assertStringContainsString('Le rôle est obligatoire', $lastErrorException->getMessage());
        }
        self::assertSame([], $this->getAnnuaireRoleSQL()->getAll(self::ID_E_COL));
    }

    /**
     * @throws LastErrorException
     */
    public function testDeleteGroupeRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E_COL, self::ID_E_COL, 'admin');
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_r' => [$id_r], 'submit' => 'Supprimer']);

        try {
            $mailSecControler->operationGroupeRoleAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        self::assertFalse($this->getAnnuaireRoleSQL()->getInfo($id_r));
    }

    /**
     * @throws LastErrorException
     */
    public function testShareGroupeRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E_COL, self::ID_E_COL, 'admin');
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_r' => [$id_r], 'submit' => 'Partager']);

        try {
            $mailSecControler->operationGroupeRoleAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        self::assertSame(1, (int)$this->getAnnuaireRoleSQL()->getInfo($id_r)['partage']);
    }

    /**
     * @throws LastErrorException
     */
    public function testUnshareGroupeRole(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E_COL, self::ID_E_COL, 'admin');
        $this->getAnnuaireRoleSQL()->partage($id_r);
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_r' => [$id_r], 'submit' => 'Ne plus partager']);

        try {
            $mailSecControler->operationGroupeRoleAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        self::assertSame(0, (int)$this->getAnnuaireRoleSQL()->getInfo($id_r)['partage']);
    }

    /**
     * @throws LastErrorException
     */
    public function testOperationUnknownGroupeRole(): void
    {
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_r' => [999], 'submit' => 'Supprimer']);

        $this->expectException(LastMessageException::class);
        $mailSecControler->operationGroupeRoleAction();
    }

    /**
     * @throws LastErrorException
     */
    public function testOperationRoleWithoutArray(): void
    {
        $id_r = $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E_COL, self::ID_E_COL, 'admin');
        $mailSecControler = $this->getMailSecControler();
        $this->setPostInfo(['id_e' => self::ID_E_COL, 'id_r' => (string)$id_r, 'submit' => 'Supprimer']);

        try {
            $mailSecControler->operationGroupeRoleAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        self::assertNotFalse($this->getAnnuaireRoleSQL()->getInfo($id_r));
    }

    /**
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testContactAjaxArrayTerm(): void
    {
        $this->getAnnuaireRoleSQL()->add('admin - Bourg-en-Bresse', self::ID_E_COL, self::ID_E_COL, 'admin');
        $mailSecControler = $this->getMailSecControler();
        $this->setGetInfo(['id_e' => self::ID_E_COL, 'term' => ['admin'], 'mail-only' => 'false']);

        $this->expectOutputRegex('/role: \\\\"admin - Bourg-en-Bresse\\\\"/');
        $mailSecControler->getContactAjaxAction();
    }
}
