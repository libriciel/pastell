<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentEntiteSQL;

final class DocumentRoleService
{
    public const EDITEUR = 'editeur';

    public function __construct(
        private readonly DocumentEntiteSQL $documentEntiteSQL,
    ) {
    }

    public function addRole(string $id_d, int $id_e, string $role): void
    {
        if ($this->hasRole($id_d, $id_e, $role)) {
            return;
        }
        $this->documentEntiteSQL->insert($id_d, $id_e, $role);
    }

    public function hasRole(string $id_d, int $id_e, string $role): bool
    {
        return $this->documentEntiteSQL->countRole($id_d, $id_e, $role) > 0;
    }

    public function getRole(int $id_e, string $id_d): ?string
    {
        return $this->documentEntiteSQL->getRole($id_e, $id_d);
    }

    public function getEntiteIdWithRole(string $id_d, string $role): ?int
    {
        return $this->documentEntiteSQL->getEntiteIdWithRole($id_d, $role);
    }
}
