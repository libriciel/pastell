<?php

class UtilisateurRoleAPIControllerTest extends PastellTestCase
{
    public function testList(): void
    {
        $list = $this->getInternalAPI()->get('/utilisateur/1/role');
        self::assertSame('1', $list[0]['id_u']);
        self::assertSame('admin', $list[0]['role']);
        self::assertSame('0', $list[0]['id_e']);
    }

    public function testAdd(): void
    {
        $this->getInternalAPI()->post('/utilisateur/1/role', ['id_e' => 0,'role' => 'utilisateur']);
        $list = $this->getInternalAPI()->get('/utilisateur/1/role');
        static::assertSame('utilisateur', $list[1]['role']);
    }

    public function testAddBadUtilisateur(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'utilisateur n'existe pas : {id_u=42}");
        $this->getInternalAPI()->post('/utilisateur/42/role', ['id_e' => 0,'role' => 'utilisateur']);
    }

    public function testAddBadRole(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Le role spécifié n'existe pas {role=foo}");
        $this->getInternalAPI()->post('/utilisateur/1/role', ['id_e' => 0,'role' => 'foo']);
    }

    public function testAddSeveral()
    {
        $this->expectOutputRegex("#ok#");
        $this->getV1("add-several-role-utilisateur.php?id_u=2&id_e=1&deleteRoles=true&role[]=utilisateur&role[]=autre");
        $list = $this->getInternalAPI()->get("utilisateur/2/role");
        $this->assertEquals('autre', $list[0]['role']);
    }

    public function testAddSeveralNoRole()
    {
        $this->expectOutputRegex('#\[#');
        $this->getV1("add-several-role-utilisateur.php?id_u=2&id_e=1");
    }

    public function testAddSeveralOneRole()
    {
        $this->expectOutputRegex("#ok#");
        $this->getV1("add-several-role-utilisateur.php?id_u=2&id_e=1&deleteRoles=true&role=autre");
        $list = $this->getInternalAPI()->get("utilisateur/2/role");
        $this->assertEquals('autre', $list[0]['role']);
    }


    public function testDelete(): void
    {
        $this->getInternalAPI()->delete('/utilisateur/2/role?id_e=1&role=admin');
        $list = $this->getInternalAPI()->get('/utilisateur/2/role');
        static::assertSame('aucun droit', $list[0]['role']);
    }

    public function testDeletePurgesNotification(): void
    {
        $notification = $this->getObjectInstancier()->getInstance(Notification::class);
        $notification->add(2, 1, 'actes-generique', Notification::ALL_TYPE, false);
        $this->getInternalAPI()->delete('/utilisateur/2/role?id_e=1&role=admin');
        static::assertEmpty($notification->getAll(2));
    }

    public function testDeleteSeveral(): void
    {
        $this->expectOutputRegex('#ok#');
        $this->getV1('delete-several-roles-utilisateur.php?id_u=2&id_e=1&role[]=admin');
        $list = $this->getInternalAPI()->get('/utilisateur/2/role');
        static::assertSame('aucun droit', $list[0]['role']);
    }

    public function testDeleteSevaralNoRole()
    {
        $this->expectOutputRegex('#\[#');
        $this->getV1("delete-several-roles-utilisateur.php?id_u=2&id_e=1");
    }

    public function testDeleteSeveralOneRole(): void
    {
        $this->expectOutputRegex('#ok#');
        $this->getV1('delete-several-roles-utilisateur.php?id_u=2&id_e=1&role=admin');
        $list = $this->getInternalAPI()->get('/utilisateur/2/role');
        static::assertSame('aucun droit', $list[0]['role']);
    }
}
