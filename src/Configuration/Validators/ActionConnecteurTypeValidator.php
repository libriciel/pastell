<?php

declare(strict_types=1);

namespace Pastell\Configuration\Validators;

use ActionExecutor;
use ConnecteurDefinitionFiles;
use Pastell\Configuration\ActionElement;
use Pastell\Configuration\DocumentTypeValidation;
use Pastell\Configuration\ModuleElement;
use Pastell\Service\Document\DocumentTransformService;

class ActionConnecteurTypeValidator implements ValidatorInterface
{
    private array $errors;

    public function __construct(
        private readonly DocumentTypeValidation $documentTypeValidation,
        private readonly ConnecteurDefinitionFiles $connecteurDefinitionFiles,
    ) {
    }

    public function validate(array $typeDefinition): bool
    {
        $this->errors = [];
        if (empty($typeDefinition[ModuleElement::ACTION->value])) {
            return true;
        }
        $allActionKeys = array_keys($typeDefinition[ModuleElement::ACTION->value]);

        foreach ($typeDefinition[ModuleElement::ACTION->value] as $actionName => $actionProperties) {
            $this->validateConnecteurType($actionProperties, $actionName);
            $this->validateConnecteurTypeAction($actionProperties, $actionName);
            $this->validateConnecteurTypeMapping($actionProperties, $allActionKeys, $actionName);
        }
        return count($this->errors) === 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validateConnecteurType(array $actionProperties, string $actionName): void
    {
        if (!isset($actionProperties[ActionElement::CONNECTEUR_TYPE->value])) {
            return;
        }
        if (
            !\in_array(
                $actionProperties[ActionElement::CONNECTEUR_TYPE->value],
                $this->connecteurDefinitionFiles->getAllType(),
                true
            )
        ) {
            $this->errors[] = "action:<b>$actionName</b>:connecteur-type:" .
                "<b>{$actionProperties[ActionElement::CONNECTEUR_TYPE->value]}</b> n'est pas un connecteur du système";
        }
    }

    private function validateConnecteurTypeAction(array $actionProperties, string $actionName): void
    {
        if (!isset($actionProperties[ActionElement::CONNECTEUR_TYPE_ACTION->value])) {
            return;
        }
        if (!is_subclass_of($actionProperties[ActionElement::CONNECTEUR_TYPE_ACTION->value], ActionExecutor::class)) {
            $this->errors[] = "action:<b>$actionName</b>:connecteur-type-action:" .
                "<b>{$actionProperties[ActionElement::CONNECTEUR_TYPE_ACTION->value]}</b> "
                . "n'est pas une classe d'action du système";
        }
    }

    private function validateConnecteurTypeMapping(
        array $actionProperties,
        array $allActionKeys,
        string $actionName
    ): void {
        if (!isset($actionProperties[ActionElement::CONNECTEUR_TYPE_MAPPING->value])) {
            return;
        }
        foreach ($actionProperties[ActionElement::CONNECTEUR_TYPE_MAPPING->value] as $key => $elementName) {
            if (
                !\in_array($elementName, $this->documentTypeValidation->getFormulaireElements(), true)
                && !\in_array($elementName, $allActionKeys, true)
            ) {
                $this->errors[] = "action:<b>$actionName</b>:connecteur-type-mapping:$key:" .
                    "<b>$elementName</b> n'est pas un élément du formulaire";
            }
        }
    }
}
