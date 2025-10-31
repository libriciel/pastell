<?php

class ExtensionAPIControllerTest extends PastellTestCase
{
    public function testList(): void
    {
        $list = $this->getInternalAPI()->get('/extension');
        static::assertSame(
            [
                'result' => [
                    1 => [
                        'path' => '/var/lib/pastell/pastell_cdg59',
                        'flux' => [],
                        'connecteur' => [],
                        'connecteur-type' => [],
                        'type_dossier' => [],
                        'manifest' => false,
                        'id' => 'pastell_cdg59',
                        'nom' => 'pastell_cdg59',
                        'error' => 'Extension non trouvée',
                        'warning' => false,
                        'pastell-version-ok' => true,
                        'id_e' => '1',
                        'error-detail' => "L'emplacement /var/lib/pastell/pastell_cdg59 n'a pas été trouvé sur le système de fichier",
                    ],
                    2 => [
                        'path' => '/var/lib/pastell/pastell_stela',
                        'flux' => [],
                        'connecteur' => [],
                        'connecteur-type' => [],
                        'type_dossier' => [],
                        'manifest' => false,
                        'id' => 'pastell_stela',
                        'nom' => 'pastell_stela',
                        'error' => 'Extension non trouvée',
                        'warning' => false,
                        'pastell-version-ok' => true,
                        'id_e' => '2',
                        'error-detail' => "L'emplacement /var/lib/pastell/pastell_stela n'a pas été trouvé sur le système de fichier"
                    ],
                ],
            ],
            $list
        );
    }

    public function testEdit(): void
    {
        $list = $this->getInternalAPI()->patch('/extension/1', ['path' => '/tmp']);
        static::assertSame([
            'id_extension' => '1',
            'detail' => [
                'path' => '/tmp',
                'flux' => [],
                'connecteur' => [],
                'connecteur-type' => [],
                'type_dossier' => [],
                'manifest' => false,
                'id' => 'tmp',
                'nom' => 'tmp',
                'error' => false,
                'warning' => 'manifest.yml absent',
                'pastell-version-ok' => true,
                'id_e' => '1',
                'warning-detail' => "Le fichier manifest.yml n'a pas été trouvé dans /tmp",
            ],
        ], $list);
    }

    public function testEditPathNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le chemin « /foo/bar » n'existe pas sur le système de fichier");
        $this->getInternalAPI()->patch('/extension/1', ['path' => '/foo/bar']);
    }

    public function testEditExtensionNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Extension #42 non trouvée');
        $this->getInternalAPI()->patch('/extension/42', ['path' => '/tmp']);
    }

    public function testEditAlreadyExists(): void
    {
        $this->getInternalAPI()->post('/extension', ['path' => __DIR__ . '/../fixtures/extensions/extension-test']);
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage("L'extension #glaneur est déja présente");
        $this->getInternalAPI()->post('/extension', ['path' => __DIR__ . '/../fixtures/extensions/extension-test']);
    }

    public function testDeleteAction(): void
    {
        $this->getInternalAPI()->delete('/extension/1');
        $list = $this->getInternalAPI()->get('/extension');
        static::assertArrayNotHasKey(1, $list['result']);
    }

    public function testDeleteActionNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Extension #42 non trouvée');
        $this->getInternalAPI()->delete('/extension/42');
    }

    public function testV1list()
    {
        $this->expectOutputRegex("#manifest#");
        $this->getV1("list-extension.php");
    }

    public function testV1create()
    {
        $this->expectOutputRegex("#manifest.yml absent#");
        $this->getV1("edit-extension.php?path=/tmp");
    }

    public function testV1edit()
    {
        $this->expectOutputRegex("#/tmp#");
        $this->getV1("edit-extension.php?path=/tmp&id_extension=1");
    }

    public function testV1delete()
    {
        $this->expectOutputRegex("#ok#");
        $this->getV1("delete-extension.php?id_extension=1");
    }

    public function testPatchAsEntiteAdministrator(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Acces interdit id_e=0, droit=system:edition,id_u=2');

        $this->getInternalAPIAsUser(2)->patch('/extension/1', ['path' => '/tmp']);
    }
}
