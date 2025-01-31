<?php

declare(strict_types=1);

namespace Pastell\Service\FeatureToggle;

use Pastell\Service\FeatureToggleDefault;

class DisplayConnecteurEntiteRacine extends FeatureToggleDefault
{
    public function getDescription(): string
    {
        return "Affichage du menu permettant de créer un connecteur d'entité au niveau de l'entité racine";
    }
}
