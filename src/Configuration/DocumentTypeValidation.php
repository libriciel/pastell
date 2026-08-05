<?php

declare(strict_types=1);

namespace Pastell\Configuration;

use ActionPossible;
use Exception;
use ObjectInstancier;
use Pastell\Configuration\Validators\ValidatorInterface;
use Pastell\Helpers\ClassHelper;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;
use UnrecoverableException;
use YMLLoader;

class DocumentTypeValidation
{
    private array $errorList = [];
    private array $allFormulaireElements;
    private ?NodeInterface $configTree = null;
    public const MODULE_DEFINITION = 'module-definition.yml';

    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
        private readonly DocumentTypeConfiguration $documentTypeConfiguration,
        private readonly YMLLoader $ymlLoader,
        private readonly string $data_dir,
    ) {
    }

    public function getModuleDefinition(): array
    {
        $moduleDefinition = $this->ymlLoader->getArray(
            $this->data_dir . '/' . self::MODULE_DEFINITION
        );
        foreach ($moduleDefinition as $part => $properties) {
            if (! isset($properties['info'])) {
                $moduleDefinition[$part]['info'] = '';
            }
            foreach ($properties['possible_key'] as $key => $keyProperties) {
                if (! isset($keyProperties['info'])) {
                    $moduleDefinition[$part]['possible_key'][$key]['info'] = '';
                }
            }
        }
        return $moduleDefinition;
    }

    public function isDefinitionFileValid(string $filePath): bool
    {
        try {
            $this->getConfiguration($filePath);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @throws UnrecoverableException
     */
    private function getConfiguration(string $filePath): void
    {
        $ymlData = $this->ymlLoader->getArray($filePath);
        if ($ymlData === false) {
            throw new UnrecoverableException("Impossible de lire $filePath");
        }
        $dataProcessed = (new Processor())->process($this->getConfigTree(), [$ymlData]);
        $this->validate($dataProcessed);
    }

    private function getConfigTree(): NodeInterface
    {
        if ($this->configTree === null) {
            $this->configTree = $this->documentTypeConfiguration->getConfigTreeBuilder()->buildTree();
        }
        return $this->configTree;
    }

    public function getErrorList(string $filePath): array
    {
        try {
            $this->getConfiguration($filePath);
        } catch (Exception $e) {
            if ($e->getMessage() !== '') {
                $this->errorList[] = $e->getMessage();
            }
        }
        return $this->errorList;
    }

    /**
     * @throws UnrecoverableException
     */
    private function validate(array $typeDefinition): void
    {
        $this->errorList = [];
        $this->allFormulaireElements = $this->getAllFormulaireElements($typeDefinition);
        $validatorClasses = $this->getAllValidatorClasses();

        foreach ($validatorClasses as $class) {
            /** @var ValidatorInterface $validator */
            $validator = $this->objectInstancier->getInstance($class);
            if (! $validator->validate($typeDefinition)) {
                $this->errorList = array_merge($this->errorList, $validator->getErrors());
            }
        }
        if (count($this->errorList) > 0) {
            throw new UnrecoverableException();
        }
    }

    private function getAllFormulaireElements(array $typeDefinition): array
    {
        $result = [];
        foreach ($typeDefinition[ModuleElement::FORMULAIRE->value] as $onglet => $elementList) {
            if (!$elementList) {
                $this->errorList[] = "formulaire:$onglet: est vide";
                continue;
            }
            $result = array_merge($result, array_keys($elementList));
        }
        return $result;
    }

    private function getAllValidatorClasses(): array
    {
        return ClassHelper::findRecursive('Pastell\Configuration\Validators');
    }

    public function getFormulaireElements(): array
    {
        return $this->allFormulaireElements;
    }

    public function getRuleList(array $typeDefinition): array
    {
        $allRule = [];
        foreach ($typeDefinition[ModuleElement::ACTION->value] as $action) {
            if (is_array($action) && isset($action[ActionElement::RULE->value])) {
                $allRule[] = $action[ActionElement::RULE->value];
            }
        }
        return $allRule;
    }

    public function getElementRuleValue(array $ruleList, string $ruleName): array
    {
        $array = [];
        foreach ($ruleList as $rulekey => $rulevalue) {
            if ($rulekey === $ruleName) {
                if (!is_array($rulevalue)) {
                    $rulevalue = [$rulevalue];
                }
                $array = array_merge($array, $rulevalue);
            }
            if (
                str_starts_with($rulekey, RuleElement::NO->value)
                || str_starts_with($rulekey, RuleElement::AND->value)
                || str_starts_with($rulekey, RuleElement::OR->value)
            ) {
                $array = array_merge($array, $this->getElementRuleValue($rulevalue, $ruleName));
            }
        }
        return $array;
    }

    public function getAllPossibleAction(array $typeDefinition): array
    {
        $allPossibleActionKeys = array_keys($typeDefinition[ModuleElement::ACTION->value]);
        if (! in_array(ActionPossible::FATAL_ERROR_ACTION, $allPossibleActionKeys, true)) {
            $allPossibleActionKeys[] = ActionPossible::FATAL_ERROR_ACTION;
        }
        return $allPossibleActionKeys;
    }

    public function getFormulairePropertiesValue(array $typeDefinition, string $property): array
    {
        if (empty($typeDefinition[ModuleElement::FORMULAIRE->value])) {
            return [];
        }
        $propertiesList = [];
        foreach ($typeDefinition[ModuleElement::FORMULAIRE->value] as $properties) {
            foreach ($properties as $elementProperties) {
                if (isset($elementProperties[$property])) {
                    $propertiesList[] = $elementProperties[$property];
                }
            }
        }
        return $propertiesList;
    }
}
