<?php

class RoleDroit
{
    private $documentTypeFactory;

    public function __construct(DocumentTypeFactory $documentTypeFactory)
    {
        $this->documentTypeFactory = $documentTypeFactory;
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
            'connecteur:edition'
        ];
        sort($droit);
        return array_merge($droit, $this->documentTypeFactory->getAllDroit());
    }

    public function areExistingRolesDroits(array $rolesDroits): bool
    {
        return count(array_intersect($rolesDroits, $this->getAllDroit())) === count($rolesDroits);
    }
}
