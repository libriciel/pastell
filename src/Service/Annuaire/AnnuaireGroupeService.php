<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use BadRequestException;
use ConflictException;
use NotFoundException;

final class AnnuaireGroupeService
{
    public function __construct(
        private AnnuaireGroupeSQL $annuaireGroupeSQL,
        private AnnuaireSQL $annuaireSQL,
    ) {
    }

    /**
     * @throws NotFoundException
     */
    public function findGroupe(int $id_g): array
    {
        $info = $this->annuaireGroupeSQL->getInfoById($id_g);
        if (!$info) {
            throw new NotFoundException("Le groupe id_g=$id_g n'existe pas");
        }
        return $info;
    }

    public function listGroupes(int $id_e): array
    {
        return $this->annuaireGroupeSQL->getGroupe($id_e);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function createGroupe(int $id_e, string $nom): array
    {
        if ($nom === '') {
            throw new BadRequestException('Le nom du groupe est obligatoire');
        }
        if ($this->annuaireGroupeSQL->getFromNom($id_e, $nom)) {
            throw new ConflictException("Un groupe \"$nom\" existe déjà");
        }
        $id_g = $this->annuaireGroupeSQL->add($id_e, $nom);
        return $this->annuaireGroupeSQL->getInfo($id_e, $id_g);
    }

    /**
     * @throws NotFoundException
     * @throws ConflictException
     */
    public function addContactToGroupe(int $id_g, int $id_a): void
    {
        $groupeInfo = $this->findGroupe($id_g);
        $id_e = (int)$groupeInfo['id_e'];

        $contactInfo = $this->annuaireSQL->getInfo($id_a);
        if (!$contactInfo) {
            throw new NotFoundException("Le contact id_a=$id_a n'existe pas");
        }
        if ((int)$contactInfo['id_e'] !== $id_e) {
            throw new NotFoundException("Le contact id_a=$id_a n'appartient pas à l'entité id_e=$id_e");
        }

        if ($this->annuaireGroupeSQL->isInGroupe($id_g, $id_a)) {
            throw new ConflictException("Le contact id_a=$id_a est déjà dans le groupe id_g=$id_g");
        }

        $this->annuaireGroupeSQL->addToGroupe($id_g, $id_a);
    }

    /**
     * @throws NotFoundException
     */
    public function removeContactFromGroupe(int $id_g, int $id_a): void
    {
        $this->findGroupe($id_g);

        if (!$this->annuaireGroupeSQL->isInGroupe($id_g, $id_a)) {
            throw new NotFoundException("Le contact id_a=$id_a n'est pas dans le groupe id_g=$id_g");
        }

        $this->annuaireGroupeSQL->deleteFromGroupe($id_g, [$id_a]);
    }

    public function deleteGroupe(int $id_e, int $id_g): void
    {
        $this->annuaireGroupeSQL->delete($id_e, $id_g);
    }
}
