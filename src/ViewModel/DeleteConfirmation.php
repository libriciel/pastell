<?php

declare(strict_types=1);

namespace Pastell\ViewModel;

final class DeleteConfirmation
{
    public const array RESSOURCE = ['ressource', 'ressources', true];
    public const array CONNECTEUR = ['connecteur', 'connecteurs', false];
    public const array ROLE = ['rôle', 'rôles', false];
    public const array EXTENSION = ['extension', 'extensions', true];
    public const array TYPE_DOSSIER = ['type de dossier', 'types de dossier', false];
    public const array ENTITE = ['entité', 'entités', true];
    public const array CONTACT = ['contact', 'contacts', false];
    public const array GROUPE = ['groupe', 'groupes', false];
    public const array FREQUENCE = ['fréquence', 'fréquences', true];
    public const array TRAVAIL = ['travail', 'travaux', false];
    public const array GESTIONNAIRE_TACHES = ['gestionnaire de tâches', 'gestionnaires de tâches', false];
    public const array NOTIFICATION = ['notification', 'notifications', true];
    public const array UTILISATEUR = ['utilisateur', 'utilisateurs', false];
    public const array JETON = ['jeton', 'jetons', false];
    public const array DOSSIER = ['dossier', 'dossiers', false];

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, scalar|array<scalar>> $formData
     * @param array{0: string, 1: string, 2: bool} $itemLabel
     */
    public function __construct(
        public readonly string $actionUrl,
        public readonly string $cancelUrl,
        public readonly array $items,
        public readonly array $formData,
        public readonly array $itemLabel = self::RESSOURCE,
        public readonly string $submitLabel = 'Supprimer la sélection',
    ) {
    }

    public function getDeletionLabel(): string
    {
        return new ResourceLabel(...$this->itemLabel)->deletionLabel(\count($this->items));
    }
}
