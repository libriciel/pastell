<?php

class FrequenceConnecteurAPIControllerTest extends PastellTestCase
{
    public function testList(): void
    {
        $result = $this->getInternalAPI()->get('/frequenceConnecteur');
        static::assertSame(
            [
                [
                    'id_cf' => '1',
                    'type_connecteur' => '',
                    'famille_connecteur' => '',
                    'id_connecteur' => '',
                    'id_ce' => '0',
                    'action_type' => '',
                    'type_document' => '',
                    'action' => '',
                    'expression' => '2',
                    'id_verrou' => 'DEFAULT_FREQUENCE',
                    'libelle' => null,
                    'denomination' => null,
                ],
                [
                    'id_cf' => '2',
                    'type_connecteur' => 'entite',
                    'famille_connecteur' => '',
                    'id_connecteur' => 'i-parapheur',
                    'id_ce' => '42',
                    'action_type' => 'document',
                    'type_document' => 'actes-generique',
                    'action' => 'verif-tdt',
                    'expression' => '30',
                    'id_verrou' => '',
                    'libelle' => null,
                    'denomination' => null,
                ],
            ],
            $result
        );
    }

    public function testDetail(): void
    {
        $result = $this->getInternalAPI()->get('/frequenceConnecteur/1');
        static::assertSame(
            [
                'id_cf' => '1',
                'type_connecteur' => '',
                'famille_connecteur' => '',
                'id_connecteur' => '',
                'id_ce' => '0',
                'action_type' => '',
                'type_document' => '',
                'action' => '',
                'expression' => '2',
                'id_verrou' => 'DEFAULT_FREQUENCE',
                'libelle' => null,
                'denomination' => null,
            ],
            $result
        );
    }

    public function testDetailNotFound()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Cette fréquence de connecteur n'existe pas");
        $this->getInternalAPI()->get("/frequenceConnecteur/12");
    }

    public function testNew(): void
    {
        $result = $this->getInternalAPI()->post(
            '/frequenceConnecteur',
            [
                'type_connecteur' => 'entite',
                'famille_connecteur' => 'signature',
                'expression' => 42,
            ]
        );
        static::assertSame(
            [
                'id_cf' => '3',
                'type_connecteur' => 'entite',
                'famille_connecteur' => 'signature',
                'id_connecteur' => '',
                'id_ce' => '0',
                'action_type' => '',
                'type_document' => '',
                'action' => '',
                'expression' => '42',
                'id_verrou' => '',
                'libelle' => null,
                'denomination' => null,
            ],
            $result
        );
    }

    public function testEdit(): void
    {
        $connecteur_frequence = $this->getInternalAPI()->get('/frequenceConnecteur/1');
        $connecteur_frequence['id_verrou'] = 'VERROU_1';
        $result = $this->getInternalAPI()->patch('/frequenceConnecteur/1', $connecteur_frequence);
        static::assertSame($connecteur_frequence, $result);
    }

    public function testDelete(): void
    {
        $return = $this->getInternalAPI()->delete('/frequenceConnecteur/1');
        static::assertSame(['result' => 'ok'], $return);
        $connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        static::assertEmpty($connecteurFrequenceSQL->getInfo(1));
    }

    public function testGetAsEntiteAdministrator()
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=daemon:lecture,id_u=2');
        $this->getInternalAPIAsUser(2)->get('/frequenceConnecteur');
    }

    public function testDetailAsEntiteAdministrator()
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=daemon:lecture,id_u=2');
        $this->getInternalAPIAsUser(2)->get('/frequenceConnecteur/1');
    }

    public function testPostAsEntiteAdministrator()
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=daemon:edition,id_u=2');
        $this->getInternalAPIAsUser(2)->post('/frequenceConnecteur', []);
    }

    public function testPatchAsEntiteAdministrator()
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=daemon:edition,id_u=2');
        $this->getInternalAPIAsUser(2)->patch('/frequenceConnecteur/1', []);
    }

    public function testDeleteAsEntiteAdministrator()
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=daemon:edition,id_u=2');
        $this->getInternalAPIAsUser(2)->delete('/frequenceConnecteur/1');
    }
}
