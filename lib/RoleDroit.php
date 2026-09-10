<?php

use Pastell\Service\Droit\DroitService;

class RoleDroit
{
    public const string SUFFIX_DESTINATAIRE = '-destinataire';
    public const string SUFFIX_REPONSE = '-reponse';

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
            static fn (string $droit): bool => !str_contains($droit, self::SUFFIX_DESTINATAIRE . ':')
                && !str_contains($droit, self::SUFFIX_REPONSE . ':')
        );
        return array_merge($droit, $documentDroit);
    }

    public function filterExistingRolesDroits(array $rolesDroits): array
    {
        return array_intersect($rolesDroits, $this->getAllDroit());
    }
}
