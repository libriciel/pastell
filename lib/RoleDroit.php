<?php

use Pastell\Service\Droit\DroitService;

class RoleDroit
{
    public function __construct(
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly bool $connectorActionPermission,
    ) {
    }

    public function getAllDroit(): array
    {
        $droit = [
            'entite:edition',
            'entite:lecture',
            'utilisateur:lecture',
            'utilisateur:edition',
            'utilisateur:creation',
            'role:lecture',
            'role:edition',
            'journal:lecture',
            'system:lecture',
            'system:edition',
            'annuaire:lecture',
            'annuaire:edition',
            'connecteur:lecture',
            'connecteur:edition',
        ];
        if ($this->connectorActionPermission) {
            $droit[] = 'connecteur:action';
        }
        sort($droit);
        return array_merge($droit, $this->documentTypeFactory->getAllDroit());
    }

    public function areExistingRolesDroits(array $rolesDroits): bool
    {
        return count(array_intersect($rolesDroits, $this->getAllDroit())) === count($rolesDroits);
    }

    public function filterExistingRolesDroits(array $rolesDroits): array
    {
        return array_intersect($rolesDroits, $this->getAllDroit());
    }
}
