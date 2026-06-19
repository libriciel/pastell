<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use BadRequestException;
use ConflictException;
use NotFoundException;
use Pastell\Service\Annuaire\AnnuaireContactService;
use PastellTestCase;
use SQLQuery;

final class AnnuaireContactServiceTest extends PastellTestCase
{
    private const ID_E = 1;

    private function getService(): AnnuaireContactService
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireContactService::class);
    }

    private function getAnnuaireSQL(): AnnuaireSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireSQL::class);
    }

    private function getAnnuaireGroupe(): AnnuaireGroupeSQL
    {
        return $this->getObjectInstancier()->getInstance(AnnuaireGroupeSQL::class);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testCreateSuccess(): void
    {
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');

        $info = $this->getAnnuaireSQL()->getInfo($id_a);
        self::assertSame('alice@example.org', $info['email']);
        self::assertSame('Alice', $info['description']);
    }

    /**
     * @throws ConflictException
     */
    public function testCreateInvalidEmail(): void
    {
        $this->expectException(BadRequestException::class);
        $this->getService()->create(self::ID_E, 'Alice', 'not-an-email');
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testCreateDuplicateEmail(): void
    {
        $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');

        $this->expectException(ConflictException::class);
        $this->getService()->create(self::ID_E, 'Alice bis', 'alice@example.org');
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testEditSuccess(): void
    {
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');
        $this->getService()->edit($id_a, 'Alice Updated', 'alice-new@example.org');

        $info = $this->getAnnuaireSQL()->getInfo($id_a);
        self::assertSame('alice-new@example.org', $info['email']);
        self::assertSame('Alice Updated', $info['description']);
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testEditSameEmailDoesNotConflict(): void
    {
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');
        $this->getService()->edit($id_a, 'Alice Updated', 'alice@example.org');

        $info = $this->getAnnuaireSQL()->getInfo($id_a);
        self::assertSame('Alice Updated', $info['description']);
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testEditInvalidEmail(): void
    {
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');

        $this->expectException(BadRequestException::class);
        $this->getService()->edit($id_a, 'Alice', 'not-an-email');
    }

    /**
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function testEditDuplicateEmail(): void
    {
        $this->getService()->create(self::ID_E, 'Bob', 'bob@example.org');
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');

        $this->expectException(ConflictException::class);
        $this->getService()->edit($id_a, 'Alice', 'bob@example.org');
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testEditContactNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->getService()->edit(9999, 'Nobody', 'nobody@example.org');
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testDeleteRemovesContact(): void
    {
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');
        $this->getService()->delete(self::ID_E, $id_a);

        self::assertFalse($this->getAnnuaireSQL()->getInfo($id_a));
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function testDeleteRemovesGroupMemberships(): void
    {
        $id_a = $this->getService()->create(self::ID_E, 'Alice', 'alice@example.org');
        $annuaireGroupe = $this->getAnnuaireGroupe();
        $id_g = $annuaireGroupe->add(self::ID_E, 'Groupe test');
        $annuaireGroupe->addToGroupe((int)$id_g, $id_a);

        self::assertNotEmpty($annuaireGroupe->getGroupeFromUtilisateur($id_a));

        $this->getService()->delete(self::ID_E, $id_a);

        self::assertEmpty($annuaireGroupe->getGroupeFromUtilisateur($id_a));
    }
}
