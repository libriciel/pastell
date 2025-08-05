<?php

class RoleDroit
{
    public function __construct(
        private readonly DocumentTypeFactory $documentTypeFactory,
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
            'connecteur:action',
            'daemon:lecture',
            'daemon:edition',
        ];
        sort($droit);
        return array_merge($droit, $this->documentTypeFactory->getAllDroit());
    }
}
