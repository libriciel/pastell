<?php

declare(strict_types=1);

namespace Pastell\Service\Annuaire;

use AnnuaireGroupeSQL;
use AnnuaireSQL;
use BadRequestException;
use ConflictException;
use NotFoundException;

final class AnnuaireContactService
{
    public function __construct(
        private AnnuaireSQL $annuaireSQL,
        private AnnuaireGroupeSQL $annuaireGroupeSQL,
    ) {
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     */
    public function create(int $id_e, string $description, string $email): int
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("$email n'est pas une adresse email valide");
        }
        if ($this->annuaireSQL->getFromEmail($id_e, $email)) {
            throw new ConflictException("$email existe déjà dans l'annuaire");
        }
        return $this->annuaireSQL->add($id_e, $description, $email);
    }

    /**
     * @throws BadRequestException
     * @throws ConflictException
     * @throws NotFoundException
     */
    public function edit(int $id_a, string $description, string $email): void
    {
        $info = $this->annuaireSQL->getInfo($id_a);
        if (!$info) {
            throw new NotFoundException("Le contact id_a=$id_a n'existe pas");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestException("$email n'est pas une adresse email valide");
        }
        if ($email !== $info['email'] && $this->annuaireSQL->getFromEmail((int)$info['id_e'], $email)) {
            throw new ConflictException("$email existe déjà dans l'annuaire");
        }
        $this->annuaireSQL->edit($id_a, $description, $email);
    }

    public function delete(int $id_e, int $id_a): void
    {
        $this->annuaireGroupeSQL->deleteAllGroupFromContact($id_a);
        $this->annuaireSQL->delete($id_e, $id_a);
    }
}
