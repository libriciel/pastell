<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class RoleControlerTest extends ControlerTestCase
{
    /** @var  RoleControler */
    private $roleControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleControler = $this->getControlerInstance("RoleControler");
    }

    public function testIndexAction()
    {
        $this->expectOutputRegex("##");
        $this->roleControler->indexAction();
    }

    public function testDetailAction()
    {
        $this->expectOutputRegex("##");
        $this->roleControler->detailAction();
    }

    public function testEditionAction()
    {
        $this->expectOutputRegex("##");
        $this->roleControler->editionAction();
    }

    public function testEditionAction2()
    {
        $this->expectOutputRegex("##");
        $_GET = ['role' => 'admin'];
        $this->roleControler->editionAction();
    }

    public function testDoEditionAction()
    {
        $this->expectException("LastMessageException");
        $this->setPostInfo(['role' => 'test','libelle' => 'test']);
        $this->roleControler->doEditionAction();
    }

    public function testDoDeleteAction()
    {
        $this->expectException("LastMessageException");
        $this->roleControler->doDeleteAction();
    }

    /**
     * @throws LastMessageException
     */
    public function testDoDeleteActionBlockedWhenRoleUsedInAnnuaire(): void
    {
        $this->expectException("LastErrorException");
        $annuaireRoleSQL = $this->getObjectInstancier()->getInstance(AnnuaireRoleSQL::class);
        $annuaireRoleSQL->add('Groupe Test', self::ID_E_COL, self::ID_E_COL, 'admin');
        $this->setPostInfo(['role' => 'admin']);
        $this->roleControler->doDeleteAction();
    }

    public function testDoDetailAction()
    {
        $this->expectException("LastMessageException");
        $this->setPostInfo(['role' => 'test','droit' => [DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::LECTURE) => 'selected']]);
        $this->roleControler->doDetailAction();
    }

    /**
     * @throws LastErrorException
     */
    public function testDoDetailActionFiltersUnknownDroits(): void
    {
        $validDroit = DroitService::getDroitFor(DroitService::DROIT_SYSTEM, DroitType::LECTURE);
        $this->setPostInfo([
            'role' => 'test',
            'droit' => [$validDroit, 'droit:inconnu'],
        ]);

        try {
            $this->roleControler->doDetailAction();
        } catch (LastMessageException) {
            /** Nothing to do */
        }

        static::assertEquals(
            [$validDroit => true],
            $this->roleControler->getRoleSQL()->getDroit([$validDroit], 'test')
        );
    }

    public function testDoEditionActionNewRole(): void
    {
        $this->setPostInfo(
            [
                'role' => 'test',
                'libelle' => 'test',
                'nouveau' => true
            ]
        );

        try {
            $this->roleControler->doEditionAction();
        } catch (LastMessageException $e) {
            /** Nothing to do */
        }

        $this->assertEquals(
            [
                DroitService::getDroitFor(DroitService::DROIT_ENTITE, DroitType::LECTURE) => 1,
                DroitService::getDroitFor(DroitService::DROIT_JOURNAL, DroitType::LECTURE) => 1,
            ],
            $this->roleControler->getRoleSQL()->getDroit([], 'test')
        );
    }

    public function testEditionActionNoInput(): void
    {
        try {
            $this->roleControler->doEditionAction();
        } catch (LastErrorException $e) {
            static::assertStringContainsString('Les deux champs sont obligatoires', $e->getMessage());
        }
    }
}
