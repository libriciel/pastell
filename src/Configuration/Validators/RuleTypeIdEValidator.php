<?php

declare(strict_types=1);

namespace Pastell\Configuration\Validators;

use Pastell\Configuration\ActionElement;
use Pastell\Configuration\DocumentTypeValidation;
use Pastell\Service\Entite\EntityType;

class RuleTypeIdEValidator implements ValidatorInterface
{
    private array $errors;

    public function __construct(
        private readonly DocumentTypeValidation $documentTypeValidation,
    ) {
    }

    public function validate(array $typeDefinition): bool
    {
        $this->errors = [];
        $allRule = $this->documentTypeValidation->getRuleList($typeDefinition);
        $allType = [];
        foreach ($allRule as $rule) {
            $allType = array_merge(
                $allType,
                $this->documentTypeValidation->getElementRuleValue($rule, ActionElement::TYPE_ID_E->value)
            );
        }
        foreach ($allType as $type) {
            if (EntityType::tryFrom($type) === null) {
                $this->errors[] = "action:*:rule:type_id_e:<b>$type</b> n'est pas un type d'entité du système";
            }
        }
        return count($this->errors) === 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
