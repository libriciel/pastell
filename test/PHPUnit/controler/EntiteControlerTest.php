<?php

use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;

class EntiteControlerTest extends ControlerTestCase
{
    /**
     * @var EntiteControler
     */
    private $entiteControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entiteControler = $this->getControlerInstance(EntiteControler::class);
    }

    public function testConnecteurAction()
    {
        $this->expectOutputRegex("#Liste des connecteurs#");
        $this->entiteControler->connecteurAction();
        $all_connecteur = $this->entiteControler->getViewParameter();
        $this->assertEquals("horodateur-interne", $all_connecteur['all_connecteur'][0]['id_connecteur']);
    }


    public function testUtilisateurAction()
    {
        $this->expectOutputRegex("#Liste des utilisateurs#");
        $this->entiteControler->utilisateurAction();
        $utilisateur_list = $this->entiteControler->getViewParameter()['liste_utilisateur'];
        $this->assertEquals('Pommateau', $utilisateur_list[0]['nom']);
    }

    /**
     * @throws Exception
     */
    public function testDetailEntite()
    {
        $this->expectOutputRegex("#Informations - Pastell#");

        $this->setGetInfo(['id_e' => 1]);
        $this->entiteControler->_beforeAction();
        $this->entiteControler->detailEntite();
        $info = $this->entiteControler->getViewParameter()['entiteExtendedInfo'];
        $this->assertEquals('Bourg-en-Bresse', $info['denomination']);
    }

    public function testExportUtilisateurAction()
    {
        $user = $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
        $id_u = $user->create('other', 'other', 'other@other.other', 'other');

        $roleUser = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        $roleUser->addRole($id_u, 'autre', 0);

        $this->setGetInfo([
            'id_e' => 0,
            'descendance' => 'on',
            'role' => 'admin',
            'search' => ''
        ]);

        ob_start();
        $this->entiteControler->exportUtilisateurAction();
        $result = ob_get_contents();
        ob_end_clean();
        $this->assertMatchesRegularExpression('/3;other;;;other@other.other/', $result);

        $this->setGetInfo([
            'id_e' => 0,
            'descendance' => 'on',
            'role_selected' => 'admin',
            'search' => ''
        ]);

        ob_start();
        $this->entiteControler->exportUtilisateurAction();
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertDoesNotMatchRegularExpression('/3;other;;;other@other.other/', $result);
    }

    public function testNumberOfUsersIsCorrect()
    {
        $this->setGetInfo([
            'id_e' => 0,
            'descendance' => 'on',
            'role' => 'does not exist',
            'search' => 'eric'
        ]);

        ob_start();
        $this->entiteControler->utilisateurAction();
        ob_end_clean();

        $info = $this->entiteControler->getViewParameter();
        $this->assertEquals(
            0,
            $info['nb_utilisateur']
        );
        $this->assertCount(
            0,
            $info['liste_utilisateur']
        );
    }

    /**
     * @throws Exception
     */
    public function testDisplayEntiteWithRoleOnRootAndChild()
    {
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole(self::ID_U_ADMIN, 'admin', self::ID_E_COL);
        $this->entiteControler->_beforeAction();
        ob_start();
        $this->entiteControler->detailAction();
        ob_end_clean();

        $info = $this->entiteControler->getViewParameter();

        $this->assertSame(1, $info['nbCollectivite']);
        $this->assertCount(1, $info['liste_collectivite']);
    }

    /**
     * @throws UnrecoverableException
     * @throws LastErrorException
     */
    public function testDoEditionAction(): void
    {
        $this->setPostInfo([
            'id_e' => 0,
            'siren' => '000000000',
            'denomination' => 'TEST ENTITIES',
            'type' => 'collectivite',
        ]);
        try {
            $this->entiteControler->_beforeAction();
            $this->entiteControler->doEditionAction();
        } catch (LastMessageException) {
        }

        $info = $this->getObjectInstancier()->getInstance(EntiteSQL::class)->getInfo(3);
        static::assertSame('TEST ENTITIES', $info['denomination']);
    }

    /**
     * @throws LastMessageException
     * @throws LastErrorException
     * @throws JsonException
     */
    public function testDoExportConfigAction(): void
    {
        $id_e = 1;
        $this->setPostInfo([
            'id_e' => $id_e
        ]);
        $generator = new UriSafeTokenGenerator();
        $password = $generator->generateToken();
        $this->getObjectInstancier()->getInstance(MemoryCache::class)->store(
            "export_configuration_password_$id_e",
            $password,
            60
        );

        $this->expectOutputRegex('/Content-type: application\/json;*/');
        $this->entiteControler->doExportConfigAction();
    }

    public function testDoEditionActionWhenModification(): void
    {
        $this->setPostInfo([
            'id_e' => 1,
            'siren' => '000000000',
            'denomination' => 'TEST ENTITIES',
            'type' => 'collectivite',
        ]);
        try {
            $this->entiteControler->_beforeAction();
            $this->entiteControler->doEditionAction();
        } catch (LastMessageException) {
        }

        $info = $this->getObjectInstancier()->getInstance(EntiteSQL::class)->getInfo(1);
        static::assertSame('TEST ENTITIES', $info['denomination']);
    }
}
