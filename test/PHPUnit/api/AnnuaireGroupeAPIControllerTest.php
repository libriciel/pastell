<?php

declare(strict_types=1);

use Pastell\Service\Utilisateur\UserCreationService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AnnuaireGroupeAPIControllerTest extends PastellTestCase
{
    private function addContact(): int
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireSQL::class)
            ->add(self::ID_E_COL, 'Jean Dupont', 'jean@example.com');
    }

    private function addGroupe(string $nom = 'test-groupe'): int
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class)
            ->add(self::ID_E_COL, $nom);
    }

    public function testListGroupes(): void
    {
        $this->addGroupe('groupe-a');
        $this->addGroupe('groupe-b');
        $result = $this->getInternalAPI()->get('/annuaire/groupe?id_e=' . self::ID_E_COL);
        $noms = array_column($result, 'nom');
        static::assertContains('groupe-a', $noms);
        static::assertContains('groupe-b', $noms);
    }

    public function testListGroupesFixtures(): void
    {
        $result = $this->getInternalAPI()->get('/annuaire/groupe?id_e=' . self::ID_E_COL);
        $noms = array_column($result, 'nom');
        static::assertContains('Mon groupe', $noms);
        static::assertContains('Elu', $noms);
    }

    public function testDetailGroupe(): void
    {
        $id_g = $this->addGroupe('mon-groupe');
        $result = $this->getInternalAPI()->get("/annuaire/groupe/$id_g");
        static::assertSame(
            [
                'id_g' => (string)$id_g,
                'id_e' => (string)self::ID_E_COL,
                'nom' => 'mon-groupe',
            ],
            $result
        );
    }

    public function testDetailGroupeNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Le groupe id_g=999 n\'existe pas');
        $this->getInternalAPI()->get('/annuaire/groupe/999');
    }

    public function testCreateGroupe(): void
    {
        $result = $this->getInternalAPI()->post('/annuaire/groupe', [
            'id_e' => self::ID_E_COL,
            'nom' => 'nouveau-groupe',
        ]);
        static::assertSame('nouveau-groupe', $result['nom']);
        static::assertSame((string)self::ID_E_COL, $result['id_e']);
        static::assertArrayHasKey('id_g', $result);
    }

    public function testCreateGroupeDuplicate(): void
    {
        $this->addGroupe('groupe-existant');
        $this->expectException(ConflictException::class);
        $this->getInternalAPI()->post('/annuaire/groupe', [
            'id_e' => self::ID_E_COL,
            'nom' => 'groupe-existant',
        ]);
    }

    public function testCreateGroupeNomVide(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Le nom du groupe est obligatoire');
        $this->getInternalAPI()->post('/annuaire/groupe', [
            'id_e' => self::ID_E_COL,
            'nom' => '',
        ]);
    }

    public function testDeleteGroupe(): void
    {
        $id_g = $this->addGroupe();
        $result = $this->getInternalAPI()->delete("/annuaire/groupe/$id_g");
        static::assertSame(['result' => 'ok'], $result);

        $this->expectException(NotFoundException::class);
        $this->getInternalAPI()->get("/annuaire/groupe/$id_g");
    }

    public function testDeleteGroupeNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getInternalAPI()->delete('/annuaire/groupe/999');
    }

    public function testAddContactToGroupe(): void
    {
        $id_g = $this->addGroupe();
        $id_a = $this->addContact();
        $result = $this->getInternalAPI()->post("/annuaire/groupe/$id_g", ['id_a' => $id_a]);
        static::assertSame(['result' => 'ok'], $result);

        $annuaireGroupe = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        static::assertTrue((bool)$annuaireGroupe->isInGroupe($id_g, $id_a));
    }

    public function testAddContactToGroupeContactNotFound(): void
    {
        $id_g = $this->addGroupe();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Le contact id_a=999 n\'existe pas');
        $this->getInternalAPI()->post("/annuaire/groupe/$id_g", ['id_a' => 999]);
    }

    public function testAddContactToGroupeGroupeNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getInternalAPI()->post('/annuaire/groupe/999', ['id_a' => 1]);
    }

    public function testRemoveContactFromGroupe(): void
    {
        $id_g = $this->addGroupe();
        $id_a = $this->addContact();
        $annuaireGroupe = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        $annuaireGroupe->addToGroupe($id_g, $id_a);
        static::assertTrue((bool)$annuaireGroupe->isInGroupe($id_g, $id_a));

        $result = $this->getInternalAPI()->delete("/annuaire/groupe/$id_g/$id_a");
        static::assertSame(['result' => 'ok'], $result);
        static::assertSame(0, $annuaireGroupe->isInGroupe($id_g, $id_a));
    }

    public function testRemoveContactFromGroupeContactNotInGroupe(): void
    {
        $id_g = $this->addGroupe();
        $id_a = $this->addContact();
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Le contact id_a=$id_a n'est pas dans le groupe id_g=$id_g");
        $this->getInternalAPI()->delete("/annuaire/groupe/$id_g/$id_a");
    }

    public function testRemoveContactFromGroupeGroupeNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Le groupe id_g=999 n\'existe pas');
        $this->getInternalAPI()->delete('/annuaire/groupe/999/1');
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testAddContactToGroupeForbidden(): void
    {
        $id_g = $this->addGroupe();
        $id_a = $this->addContact();
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->post("/annuaire/groupe/$id_g", ['id_a' => $id_a]);
    }

    /**
     * @throws UnrecoverableException
     * @throws TransportExceptionInterface
     * @throws ConflictException
     */
    public function testDeleteGroupeForbidden(): void
    {
        $id_g = $this->addGroupe();
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->delete("/annuaire/groupe/$id_g");
    }

    public function testRemoveContactFromGroupeForbidden(): void
    {
        $id_g = $this->addGroupe();
        $id_a = $this->addContact();
        $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class)->addToGroupe($id_g, $id_a);
        $id_u = $this->getObjectInstancier()->getInstance(UserCreationService::class)
            ->create('user-sans-droit', 'user@example.com', 'User', 'Sans Droit');
        $this->expectException(ForbiddenException::class);
        $this->getInternalAPIAsUser($id_u)->delete("/annuaire/groupe/$id_g/$id_a");
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
        $this->getInternalAPIAsUser($id_u)->get('/annuaire/groupe?id_e=' . self::ID_E_COL);
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
        $this->getInternalAPIAsUser($id_u)->post('/annuaire/groupe', [
            'id_e' => self::ID_E_COL,
            'nom' => 'test',
        ]);
    }
}
