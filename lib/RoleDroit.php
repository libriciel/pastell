<?php

use Pastell\Service\Droit\DroitService;

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
            'utilisateur:suppression',
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
        $documentDroit = array_filter(
            $this->documentTypeFactory->getAllDroit(),
            static fn (string $droit): bool => !str_contains($droit, '-destinataire:') && !str_contains($droit, '-reponse:')
        );
        return array_merge($droit, $documentDroit);
    }

    /**
     * @deprecated 4.1.22 no longer used, to be deleted in 6.0.0
     */
    public function areExistingRolesDroits(array $rolesDroits): bool
    {
        return count(array_intersect($rolesDroits, $this->getAllDroit())) === count($rolesDroits);
    }

    public function filterExistingRolesDroits(array $rolesDroits): array
    {
        return array_intersect($rolesDroits, $this->getAllDroit());
    }
}
