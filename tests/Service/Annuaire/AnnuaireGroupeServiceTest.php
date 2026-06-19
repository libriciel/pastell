<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use BadRequestException;
use ConflictException;
use NotFoundException;
use Pastell\Service\Annuaire\AnnuaireGroupeService;
use PastellTestCase;
use SQLQuery;

final class AnnuaireGroupeServiceTest extends PastellTestCase
{
    private const ID_E = 1;
    private const ID_E_EMPTY = 2;

    private function getService(): AnnuaireGroupeService
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeService::class);
    }

    private function createContact(): int
    {
        return (int)$this->getObjectInstancier()->getInstance(AnnuaireSQL::class)
            ->add(self::ID_E, 'Alice', 'alice@example.org');
    }

    public function testFindGroupeNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getService()->findGroupe(9999);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testListGroupes(): void
    {
        $this->getService()->createGroupe(self::ID_E_EMPTY, 'Groupe A');
        $this->getService()->createGroupe(self::ID_E_EMPTY, 'Groupe B');

        self::assertCount(2, $this->getService()->listGroupes(self::ID_E_EMPTY));
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testCreateGroupeSuccess(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Nouveau groupe');

        self::assertSame('Nouveau groupe', $info['nom']);
        self::assertSame((string)self::ID_E, (string)$info['id_e']);
    }

    /**
     * @throws ConflictException
     */
    public function testCreateGroupeEmptyNom(): void
    {
        $this->expectException(BadRequestException::class);
        $this->getService()->createGroupe(self::ID_E, '');
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testCreateGroupeDuplicate(): void
    {
        $this->getService()->createGroupe(self::ID_E, 'Nouveau groupe');

        $this->expectException(ConflictException::class);
        $this->getService()->createGroupe(self::ID_E, 'Nouveau groupe');
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testAddContactToGroupeSuccess(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Groupe');
        $id_g = (int)$info['id_g'];
        $id_a = $this->createContact();

        $this->getService()->addContactToGroupe($id_g, $id_a);

        $annuaireGroupe = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        self::assertNotEmpty($annuaireGroupe->getGroupeFromUtilisateur($id_a));
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testAddContactToGroupeAlreadyIn(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Groupe');
        $id_g = (int)$info['id_g'];
        $id_a = $this->createContact();
        $this->getService()->addContactToGroupe($id_g, $id_a);

        $this->expectException(ConflictException::class);
        $this->getService()->addContactToGroupe($id_g, $id_a);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testAddContactToGroupeContactNotFound(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Groupe');

        $this->expectException(NotFoundException::class);
        $this->getService()->addContactToGroupe((int)$info['id_g'], 9999);
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testRemoveContactFromGroupeSuccess(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Groupe');
        $id_g = (int)$info['id_g'];
        $id_a = $this->createContact();
        $this->getService()->addContactToGroupe($id_g, $id_a);

        $this->getService()->removeContactFromGroupe($id_g, $id_a);

        $annuaireGroupe = $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
        self::assertEmpty($annuaireGroupe->getGroupeFromUtilisateur($id_a));
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testRemoveContactFromGroupeNotIn(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Groupe');
        $id_a = $this->createContact();

        $this->expectException(NotFoundException::class);
        $this->getService()->removeContactFromGroupe((int)$info['id_g'], $id_a);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testDeleteGroupe(): void
    {
        $info = $this->getService()->createGroupe(self::ID_E, 'Groupe');
        $id_g = (int)$info['id_g'];

        $this->getService()->deleteGroupe(self::ID_E, $id_g);

        $this->expectException(NotFoundException::class);
        $this->getService()->findGroupe($id_g);
    }
}
