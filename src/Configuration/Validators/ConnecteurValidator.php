<?php

declare(strict_types=1);

namespace Pastell\Configuration\Validators;

use ConnecteurDefinitionFiles;
use Pastell\Configuration\ModuleElement;

class ConnecteurValidator implements ValidatorInterface
{
    private array $errors;

    public function __construct(
        private readonly ConnecteurDefinitionFiles $connecteurDefinitionFiles,
    ) {
    }

    public function validate(array $typeDefinition): bool
    {
        $this->errors = [];
        $allConnecteur = $typeDefinition[ModuleElement::CONNECTEUR->value];
        $types = $this->connecteurDefinitionFiles->getAllType();
        foreach ($allConnecteur as $connecteur) {
            if (!\in_array($connecteur, $types, true)) {
                $this->errors[] = "connecteur:<b>$connecteur</b> n'est défini dans aucun connecteur du système";
            }
        }
        return count($this->errors) === 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
