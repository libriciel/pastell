<?php

declare(strict_types=1);

class MailSecControlerTest extends ControlerTestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

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
        $mailsecController->contactImportAction();
        \ob_end_clean();
        $view_parameter = $mailsecController->getViewParameter();

        $this->assertSame(0, $view_parameter['id_e']);
        $this->assertSame('Annuaire global', $view_parameter['infoEntite']['denomination']);
    }

    public function testGroupeSuppressionMultiple(): void
    {
        $annuaireGroupeSQL = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        $id_g1 = $annuaireGroupeSQL->add(0, 'Groupe 1');
        $id_g2 = $annuaireGroupeSQL->add(0, 'Groupe 2');

        $_POST = ['id_e' => 0, 'id_g' => [$id_g1, $id_g2]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doGroupeSuppressionAction();
            static::fail('Une LastMessageException était attendue (redirection)');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('2 groupes ont été supprimés', $e->getMessage());
            static::assertStringNotContainsString('non supprimés', $e->getMessage());
        }

        static::assertFalse($annuaireGroupeSQL->getInfo(0, $id_g1));
        static::assertFalse($annuaireGroupeSQL->getInfo(0, $id_g2));
    }

    public function testGroupeSuppressionContinueMalgreErreur(): void
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        $failingGroupeSQL = new class ($sqlQuery) extends AnnuaireGroupeSQL {
            public ?int $failId = null;
            public function delete(int $id_e, int $id_g): void
            {
                if ($id_g === $this->failId) {
                    throw new \RuntimeException('Erreur simulée sur la suppression du groupe');
                }
                parent::delete($id_e, $id_g);
            }
        };
        $this->getObjectInstancier()->setInstance(AnnuaireGroupeSQL::class, $failingGroupeSQL);

        $id_g1 = $failingGroupeSQL->add(0, 'Groupe 1');
        $id_g2 = $failingGroupeSQL->add(0, 'Groupe 2');
        $failingGroupeSQL->failId = $id_g2;

        $_POST = ['id_e' => 0, 'id_g' => [$id_g1, $id_g2]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doGroupeSuppressionAction();
            static::fail('Une LastMessageException était attendue (redirection)');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('Le groupe a été supprimé', $e->getMessage());
            static::assertStringContainsString('1 non supprimés', $e->getMessage());
        }

        static::assertFalse($failingGroupeSQL->getInfo(0, $id_g1));
        static::assertIsArray($failingGroupeSQL->getInfo(0, $id_g2));
    }

    public function testContactSuppressionMultiple(): void
    {
        $annuaireSQL = $this->getObjectInstancier()->getInstance(AnnuaireSQL::class);
        $id_a1 = $annuaireSQL->add(0, 'Contact 1', 'contact1@example.org');
        $id_a2 = $annuaireSQL->add(0, 'Contact 2', 'contact2@example.org');

        $_POST = ['id_e' => 0, 'id_a' => [$id_a1, $id_a2]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doContactSuppressionAction();
            static::fail('Une LastMessageException était attendue (redirection)');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('2 contacts ont été supprimés', $e->getMessage());
            static::assertStringNotContainsString('non supprimés', $e->getMessage());
        }

        static::assertFalse($annuaireSQL->getInfo($id_a1));
        static::assertFalse($annuaireSQL->getInfo($id_a2));
    }

    public function testContactSuppressionContinueMalgreErreur(): void
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        $failingAnnuaireSQL = new class ($sqlQuery) extends AnnuaireSQL {
            public ?int $failId = null;
            public function delete($id_e, $id_a): void
            {
                if ((int)$id_a === $this->failId) {
                    throw new \RuntimeException('Erreur simulée sur la suppression du contact');
                }
                parent::delete($id_e, $id_a);
            }
        };
        $this->getObjectInstancier()->setInstance(AnnuaireSQL::class, $failingAnnuaireSQL);

        $id_a1 = $failingAnnuaireSQL->add(0, 'Contact 1', 'contact1@example.org');
        $id_a2 = $failingAnnuaireSQL->add(0, 'Contact 2', 'contact2@example.org');
        $failingAnnuaireSQL->failId = $id_a2;

        $_POST = ['id_e' => 0, 'id_a' => [$id_a1, $id_a2]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doContactSuppressionAction();
            static::fail('Une LastMessageException était attendue (redirection)');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('Le contact a été supprimé', $e->getMessage());
            static::assertStringContainsString('1 non supprimés', $e->getMessage());
        }

        static::assertFalse($failingAnnuaireSQL->getInfo($id_a1));
        static::assertIsArray($failingAnnuaireSQL->getInfo($id_a2));
    }

    public function testContactSuppressionToutEnEchec(): void
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        $failingAnnuaireSQL = new class ($sqlQuery) extends AnnuaireSQL {
            public function delete($id_e, $id_a): void
            {
                throw new \RuntimeException('Erreur simulée sur la suppression du contact');
            }
        };
        $this->getObjectInstancier()->setInstance(AnnuaireSQL::class, $failingAnnuaireSQL);

        $id_a1 = $failingAnnuaireSQL->add(0, 'Contact 1', 'contact1@example.org');
        $id_a2 = $failingAnnuaireSQL->add(0, 'Contact 2', 'contact2@example.org');

        $_POST = ['id_e' => 0, 'id_a' => [$id_a1, $id_a2]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doContactSuppressionAction();
            static::fail('Une LastErrorException était attendue (aucun contact supprimé)');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('0 contacts ont été supprimés', $e->getMessage());
            static::assertStringContainsString('2 non supprimés', $e->getMessage());
        }
    }

    public function testGroupeSuppressionToutEnEchec(): void
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        $failingGroupeSQL = new class ($sqlQuery) extends AnnuaireGroupeSQL {
            public function delete(int $id_e, int $id_g): void
            {
                throw new \RuntimeException('Erreur simulée sur la suppression du groupe');
            }
        };
        $this->getObjectInstancier()->setInstance(AnnuaireGroupeSQL::class, $failingGroupeSQL);

        $id_g1 = $failingGroupeSQL->add(0, 'Groupe 1');
        $id_g2 = $failingGroupeSQL->add(0, 'Groupe 2');

        $_POST = ['id_e' => 0, 'id_g' => [$id_g1, $id_g2]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doGroupeSuppressionAction();
            static::fail('Une LastErrorException était attendue (aucun groupe supprimé)');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('0 groupes ont été supprimés', $e->getMessage());
            static::assertStringContainsString('2 non supprimés', $e->getMessage());
        }
    }

    public function testGroupeSuppressionIgnoreGroupeInexistant(): void
    {
        $annuaireGroupeSQL = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        $id_g1 = $annuaireGroupeSQL->add(0, 'Groupe 1');
        $id_g_inexistant = $id_g1 + 999;

        $_POST = ['id_e' => 0, 'id_g' => [$id_g1, $id_g_inexistant]];
        $controler = $this->getControlerInstance(MailSecControler::class);

        try {
            $controler->doGroupeSuppressionAction();
            static::fail('Une LastMessageException était attendue (redirection)');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('Le groupe a été supprimé', $e->getMessage());
            static::assertStringNotContainsString('non supprimés', $e->getMessage());
        }

        static::assertFalse($annuaireGroupeSQL->getInfo(0, $id_g1));
    }
}
