<?php

use Pastell\Service\Utilisateur\UserCreationService;

class EntiteAPIControllerTest extends PastellTestCase
{
    public function testList(): void
    {
        $list = $this->getInternalAPI()->get('/entite');
        static::assertSame(
            [
                'id_e' => '1',
                'denomination' => 'Bourg-en-Bresse',
                'siren' => '000000000',
                'type' => 'collectivite',
                'centre_de_gestion' => '0',
                'entite_mere' => '0',
                'is_active' => true,
            ],
            $list[0]
        );
    }

    public function testCreate(): void
    {
        $result = $this->getInternalAPI()->post(
            '/entite',
            [
                'denomination' => 'Métropolis',
                'type' => 'collectivite',
                'siren' => '677203002'
            ]
        );
        static::assertSame(
            [
                'id_e' => '3',
                'denomination' => 'Métropolis',
                'siren' => '677203002',
                'type' => 'collectivite',
                'entite_mere' => '0',
                'entite_fille' => [],
                'centre_de_gestion' => '0',
                'is_active' => true,
            ],
            $result
        );
    }

    public function testDelete()
    {
        $this->getInternalAPI()->delete("/entite/2");
        $this->expectException("NotFoundException");
        $this->expectExceptionMessage("L'entité 2 n'a pas été trouvée");
        $this->getInternalAPI()->get("/entite/2");
    }

    public function testEdit(): void
    {
        $info = $this->getInternalAPI()->patch(
            '/entite/1',
            ['denomination' => 'Mâcon', 'siren' => '677203002']
        );
        static::assertSame(
            [
                'id_e' => '1',
                'denomination' => 'Mâcon',
                'siren' => '677203002',
                'type' => 'collectivite',
                'entite_mere' => '0',
                'entite_fille' => [
                    [
                        'id_e' => '2',
                    ],
                ],
                'centre_de_gestion' => '0',
                'is_active' => true,
                'result' => 'ok',
            ],
            $info
        );
    }

    public function testDetail(): void
    {
        $info = $this->getInternalAPI()->get('/entite/1');
        static::assertSame(
            [
                'id_e' => '1',
                'denomination' => 'Bourg-en-Bresse',
                'siren' => '000000000',
                'type' => 'collectivite',
                'entite_mere' => '0',
                'entite_fille' => [
                    [
                        'id_e' => '2',
                    ],
                ],
                'centre_de_gestion' => '0',
                'is_active' => true,
            ],
            $info
        );
    }

    public function testCreateFilleCDG(): void
    {
        $this->getObjectInstancier()->getInstance(EntiteSQL::class)->update(
            self::ID_E_COL,
            'name',
            '',
            EntiteSQL::TYPE_CENTRE_DE_GESTION
        );
        $info = $this->getInternalAPI()->post(
            '/entite',
            [
                'id_e' => 2,
                'denomination' => 'Métropolis',
                'type' => 'collectivite',
                'siren' => '677203002',
                'centre_de_gestion' => self::ID_E_COL,
            ]
        );
        static::assertSame(
            [
                'id_e' => '3',
                'denomination' => 'Métropolis',
                'siren' => '677203002',
                'type' => 'collectivite',
                'entite_mere' => '0',
                'entite_fille' => [],
                'centre_de_gestion' => '1',
                'is_active' => true,
            ],
            $info
        );
    }

    public function testCreateFille(): void
    {
        $info = $this->getInternalAPI()->patch(
            '/entite/2',
            [
                'denomination' => 'Métropolis',
                'type' => 'collectivite',
                'siren' => '677203002',
                'entite_mere' => 1,
            ]
        );
        static::assertSame('1', $info['entite_mere']);
    }

    public function testCreateWithoutName()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Le nom (denomination) est obligatoire");
        $this->getInternalAPI()->post("/entite");
    }

    public function testCreateWithoutSiren()
    {
        $info = $this->getInternalAPI()->post(
            "/entite",
            [
                "denomination" => "toto",
                'type' => EntiteSQL::TYPE_COLLECTIVITE
            ]
        );
        $this->assertSame("", $info['siren']);
    }

    public function testCreateBadSiren()
    {
        $this->expectException("Exception");
        $this->expectExceptionMessage("Le siren « 123456789 » ne semble pas valide");
        $this->getInternalAPI()->post(
            "/entite",
            [
                "denomination" => "toto",
                'type' => EntiteSQL::TYPE_COLLECTIVITE,
                'siren' => '123456789'
            ]
        );
    }

    public function testActivateDeactivate(): void
    {
        $entiteActivated = $this->getInternalAPI()->get('/entite/1');
        $entiteDeactivated = $this->getInternalAPI()->post('/entite/1/deactivate');
        $this->assertNotEquals($entiteActivated, $entiteDeactivated);

        $entiteReactivated = $this->getInternalAPI()->post('/entite/1/activate');
        static::assertSame($entiteActivated, $entiteReactivated);
    }

    public function testDeActivateFailDroit(): void
    {
        $user = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('tester', 'tester@example.org', 'tester', 'tester');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->edit('entite:lecture', 'entiteLectureEdition');
        $this->getObjectInstancier()->getInstance(RoleSQL::class)
            ->edit('entite:edition', 'entiteLectureEdition');
        $this->getObjectInstancier()->getInstance(RoleUtilisateur::class)
            ->addRole('3', 'entiteLectureEdition', '1');
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=1, droit=entite:edition,id_u=3');
        $this->getInternalAPIAsUser($user)->post('/entite/1/deactivate');
    }
}
