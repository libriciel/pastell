<?php

declare(strict_types=1);

use Pastell\Service\Utilisateur\UserCreationService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AnnuaireAPIControllerTest extends PastellTestCase
{
    private function getAnnuaireSQL(): AnnuaireSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireSQL::class);
    }

    private function addContact(string $description = 'Jean Dupont', string $email = 'jean@example.com'): int
    {
        return $this->getAnnuaireSQL()->add(self::ID_E_COL, $description, $email);
    }

    public function testList(): void
    {
        $this->addContact();
        $result = $this->getInternalAPI()->get('/annuaire?id_e=' . self::ID_E_COL);
        static::assertCount(1, $result);
        static::assertSame(
            [
                'id_a' => '1',
                'id_e' => '1',
                'description' => 'Jean Dupont',
                'email' => 'jean@example.com',
            ],
            $result[0]
        );
    }

    public function testListWithSearch(): void
    {
        $this->addContact('Jean Dupont', 'jean@example.com');
        $this->addContact('Marie Curie', 'marie@example.com');
        $result = $this->getInternalAPI()->get('/annuaire?id_e=' . self::ID_E_COL . '&search=marie');
        static::assertCount(1, $result);
        static::assertSame('marie@example.com', $result[0]['email']);
    }

    public function testListEmpty(): void
    {
        $result = $this->getInternalAPI()->get('/annuaire?id_e=' . self::ID_E_COL);
        static::assertSame([], $result);
    }

    public function testDetail(): void
    {
        $id_a = $this->addContact();
        $result = $this->getInternalAPI()->get("/annuaire/$id_a");
        static::assertSame(
            [
                'id_a' => (string)$id_a,
                'id_e' => '1',
                'description' => 'Jean Dupont',
                'email' => 'jean@example.com',
            ],
            $result
        );
    }

    public function testDetailNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Le contact id_a=999 n\'existe pas');
        $this->getInternalAPI()->get('/annuaire/999');
    }

    public function testCreate(): void
    {
        $result = $this->getInternalAPI()->post('/annuaire', [
            'id_e' => self::ID_E_COL,
            'description' => 'Jean Dupont',
            'email' => 'jean@example.com',
        ]);
        static::assertSame(
            [
                'id_a' => '1',
                'id_e' => '1',
                'description' => 'Jean Dupont',
                'email' => 'jean@example.com',
            ],
            $result
        );
    }

    public function testCreateInvalidEmail(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('pas-un-email n\'est pas une adresse email valide');
        $this->getInternalAPI()->post('/annuaire', [
            'id_e' => self::ID_E_COL,
            'description' => 'Test',
            'email' => 'pas-un-email',
        ]);
    }

    public function testCreateDuplicateEmail(): void
    {
        $this->addContact();
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('jean@example.com existe déjà dans l\'annuaire');
        $this->getInternalAPI()->post('/annuaire', [
            'id_e' => self::ID_E_COL,
            'description' => 'Autre Jean',
            'email' => 'jean@example.com',
        ]);
    }

    public function testPatch(): void
    {
        $id_a = $this->addContact();
        $result = $this->getInternalAPI()->patch("/annuaire/$id_a", [
            'description' => 'Jean Modifié',
            'email' => 'nouveau@example.com',
        ]);
        static::assertSame(
            [
                'id_a' => (string)$id_a,
                'id_e' => '1',
                'description' => 'Jean Modifié',
                'email' => 'nouveau@example.com',
                'result' => 'ok',
            ],
            $result
        );
    }

    public function testPatchDescriptionOnly(): void
    {
        $id_a = $this->addContact();
        $result = $this->getInternalAPI()->patch("/annuaire/$id_a", ['description' => 'Nouveau Nom']);
        static::assertSame('Nouveau Nom', $result['description']);
        static::assertSame('jean@example.com', $result['email']);
    }

    public function testPatchEmailConflict(): void
    {
        $id_a = $this->addContact('Jean Dupont', 'jean@example.com');
        $this->addContact('Marie Curie', 'marie@example.com');
        $this->expectException(ConflictException::class);
        $this->getInternalAPI()->patch("/annuaire/$id_a", ['email' => 'marie@example.com']);
    }

    public function testPatchNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getInternalAPI()->patch('/annuaire/999', ['description' => 'test']);
    }

    public function testDelete(): void
    {
        $id_a = $this->addContact();
        $result = $this->getInternalAPI()->delete("/annuaire/$id_a");
        static::assertSame(['result' => 'ok'], $result);

        $this->expectException(NotFoundException::class);
        $this->getInternalAPI()->get("/annuaire/$id_a");
    }

    public function testDeleteRemovesGroupMembership(): void
    {
        $id_a = $this->addContact();
        $annuaireGroupe = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        $id_g = $annuaireGroupe->add(self::ID_E_COL, 'test-groupe');
        $annuaireGroupe->addToGroupe($id_g, $id_a);
        static::assertTrue((bool)$annuaireGroupe->isInGroupe($id_g, $id_a));

        $this->getInternalAPI()->delete("/annuaire/$id_a");

        static::assertSame(0, $annuaireGroupe->isInGroupe($id_g, $id_a));
    }

    public function testDeleteNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getInternalAPI()->delete('/annuaire/999');
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testListForbidden(): void
    {
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->get('/annuaire?id_e=' . self::ID_E_COL);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testCreateForbidden(): void
    {
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->post('/annuaire', [
            'id_e' => self::ID_E_COL,
            'description' => 'Test',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testPatchForbidden(): void
    {
        $id_a = $this->addContact();
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->patch("/annuaire/$id_a", ['description' => 'Test']);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testDeleteForbidden(): void
    {
        $id_a = $this->addContact();
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->delete("/annuaire/$id_a");
    }

    private function postImport(string $csv_content, int $id_e = self::ID_E_COL): array
    {
        $fileUploader = new FileUploaderMock();
        $fileUploader->setFiles(['csv' => $csv_content]);
        $this->getInternalAPI()->setFileUploader($fileUploader);

        return $this->getInternalAPI()->post('/annuaire/import', ['id_e' => $id_e]);
    }

    public function testImportOne(): void
    {
        $result = $this->postImport('eric@sigmalis.com,Eric Pommateau');
        static::assertSame(['nb_import' => 1], $result);

        $list = $this->getAnnuaireSQL()->getUtilisateur(self::ID_E_COL);
        static::assertCount(1, $list);
        static::assertSame('eric@sigmalis.com', $list[0]['email']);
    }

    public function testImportTwo(): void
    {
        $result = $this->postImport("eric@sigmalis.com,Eric Pommateau\ntoto@toto.fr,Toto");
        static::assertSame(['nb_import' => 2], $result);
    }

    public function testImportUpdatesExisting(): void
    {
        $this->postImport('eric@sigmalis.com,Eric Pommateau');
        $this->postImport('eric@sigmalis.com,Eric B. Pommateau');

        $list = $this->getAnnuaireSQL()->getUtilisateur(self::ID_E_COL);
        static::assertCount(1, $list);
        static::assertSame('Eric B. Pommateau', $list[0]['description']);
    }

    public function testImportEmpty(): void
    {
        $result = $this->postImport('');
        static::assertSame(['nb_import' => 0], $result);
    }

    public function testImportMissingFile(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Fichier CSV manquant');

        $fileUploader = new FileUploaderMock();
        $fileUploader->setFiles(['csv' => false]);
        $this->getInternalAPI()->setFileUploader($fileUploader);
        $this->getInternalAPI()->post('/annuaire/import', ['id_e' => self::ID_E_COL]);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testImportForbidden(): void
    {
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);

        $fileUploader = new FileUploaderMock();
        $fileUploader->setFiles(['csv' => 'eric@sigmalis.com,Eric']);
        $this->getInternalAPIAsUser($id_u)->setFileUploader($fileUploader);
        $this->getInternalAPIAsUser($id_u)->post('/annuaire/import', ['id_e' => self::ID_E_COL]);
    }

    public function testExport(): void
    {
        $this->getAnnuaireSQL()->add(self::ID_E_COL, 'Eric Pommateau', 'eric@sigmalis.com');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Exit called with code 0');
        $this->expectOutputRegex('#eric@sigmalis\.com,"Eric Pommateau"#');

        $this->getInternalAPI()->get('/annuaire/export', ['id_e' => self::ID_E_COL]);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testExportForbidden(): void
    {
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->get('/annuaire/export', ['id_e' => self::ID_E_COL]);
    }
}
