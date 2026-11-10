<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur\Frequence;

final class ConnecteurFrequenceData
{
    public function __construct(
        public readonly ?int $id_cf = null,
        public readonly string $type_connecteur = '',
        public readonly string $famille_connecteur = '',
        public readonly string $id_connecteur = '',
        public readonly ?int $id_ce = null,
        public readonly string $action_type = '',
        public readonly string $type_document = '',
        public readonly string $action = '',
        public readonly string $expression = '',
        public readonly string $id_verrou = '',
        public readonly string $libelle = '',
        public readonly string $denomination = '',
    ) {
    }

    public static function fromArray(array $connecteur_frequence_info): self
    {
        return new self(
            id_cf: empty($connecteur_frequence_info['id_cf']) ? null : (int)$connecteur_frequence_info['id_cf'],
            type_connecteur: (string)($connecteur_frequence_info['type_connecteur'] ?? ''),
            famille_connecteur: (string)($connecteur_frequence_info['famille_connecteur'] ?? ''),
            id_connecteur: (string)($connecteur_frequence_info['id_connecteur'] ?? ''),
            id_ce: empty($connecteur_frequence_info['id_ce']) ? null : (int)$connecteur_frequence_info['id_ce'],
            action_type: (string)($connecteur_frequence_info['action_type'] ?? ''),
            type_document: (string)($connecteur_frequence_info['type_document'] ?? ''),
            action: (string)($connecteur_frequence_info['action'] ?? ''),
            expression: (string)($connecteur_frequence_info['expression'] ?? ''),
            id_verrou: (string)($connecteur_frequence_info['id_verrou'] ?? ''),
            libelle: (string)($connecteur_frequence_info['libelle'] ?? ''),
            denomination: (string)($connecteur_frequence_info['denomination'] ?? ''),
        );
    }

    public function withFrequency(string $expression, string $id_verrou): self
    {
        return new self(
            id_cf: $this->id_cf,
            type_connecteur: $this->type_connecteur,
            famille_connecteur: $this->famille_connecteur,
            id_connecteur: $this->id_connecteur,
            id_ce: $this->id_ce,
            action_type: $this->action_type,
            type_document: $this->type_document,
            action: $this->action,
            expression: $expression,
            id_verrou: $id_verrou,
            libelle: $this->libelle,
            denomination: $this->denomination,
        );
    }
}
