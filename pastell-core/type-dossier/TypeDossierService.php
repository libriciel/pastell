<?php

use Pastell\Configuration\ElementType;
use Pastell\Service\TypeDossier\TypeDossierEditionService;
use Pastell\Service\TypeDossier\TypeDossierManager;

class TypeDossierService
{
    private $typeDossierPersonnaliseDirectoryManager;
    private $typeDossierEtapeDefinition;
    private $typeDossierEditionService;
    private $typeDossierManager;
    private $typeDossierSQL;
    private $pastellLogger;

    public function __construct(
        TypeDossierPersonnaliseDirectoryManager $typeDossierPersonnaliseDirectoryManager,
        TypeDossierEtapeManager $typeDossierEtapeDefinition,
        TypeDossierEditionService $typeDossierEditionService,
        TypeDossierManager $typeDossierManager,
        TypeDossierSQL $typeDossierSQL,
        PastellLogger $pastellLogger
    ) {
        $this->typeDossierPersonnaliseDirectoryManager = $typeDossierPersonnaliseDirectoryManager;
        $this->typeDossierEtapeDefinition = $typeDossierEtapeDefinition;
        $this->typeDossierEditionService = $typeDossierEditionService;
        $this->typeDossierManager = $typeDossierManager;
        $this->typeDossierSQL = $typeDossierSQL;
        $this->pastellLogger = $pastellLogger;
    }

    public function getFormulaireElement($id_t, $element_id)
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        return $this->getFormulaireElementFromProperties($typeDossierData, $element_id);
    }

    public function getFormulaireElementFromProperties(TypeDossierProperties $typeDossierProperties, $element_id)
    {
        foreach ($typeDossierProperties->formulaireElement as $formulaireElementProperties) {
            if ($formulaireElementProperties->element_id == $element_id) {
                return $formulaireElementProperties;
            }
        }
        return new TypeDossierFormulaireElementProperties();
    }

    public function hasFormulaireElement($typeDossierProperties, $element_id)
    {
        foreach ($typeDossierProperties->formulaireElement as $formulaireElementProperties) {
            if ($formulaireElementProperties->element_id == $element_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $typeDossierProperties
     * @param $element_id
     * @return int|string
     * @throws TypeDossierException
     */
    public function getFormulaireElementIndex($typeDossierProperties, $element_id)
    {
        foreach ($typeDossierProperties->formulaireElement as $i => $formulaireElementProperties) {
            if ($formulaireElementProperties->element_id == $element_id) {
                return $i;
            }
        }
        throw new TypeDossierException("L'élement $element_id n'existe pas");
    }

    /**
     * @param $id_t
     * @param Recuperateur $recuperateur
     * @throw TypeDossierException
     * @throws Exception
     */
    public function editionElement($id_t, Recuperateur $recuperateur): void
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);

        $element_id = $recuperateur->get('element_id');
        if (!$element_id) {
            throw new TypeDossierException("L'identifiant de l'élément est obligatoire");
        }
        $orig_element_id = $recuperateur->get('orig_element_id');
        if ($orig_element_id && $orig_element_id != $element_id) {
            $element = $this->getFormulaireElementFromProperties($typeDossierData, $orig_element_id);
            $element->element_id = $element_id;
        }
        if (!$orig_element_id && $this->hasFormulaireElement($typeDossierData, $element_id)) {
            throw new TypeDossierException(sprintf(
                "L'identifiant « %s » existe déjà sur ce formulaire",
                get_hecho($element_id)
            ));
        }

        if ($recuperateur->get('titre')) {
            foreach ($typeDossierData->formulaireElement as $formulaireElement) {
                $formulaireElement->titre = false;
            }
        }

        if ($recuperateur->get('type') === ElementType::TEXT->value && $recuperateur->get('default_value') !== '') {
            if (
                $recuperateur->get('preg_match')
                && !preg_match($recuperateur->get('preg_match'), $recuperateur->get('default_value'))
            ) {
                throw new TypeDossierException('La valeur par défaut ne répond pas à l\'expression régulière.');
            }
        }

        if ($recuperateur->get('type') === ElementType::SELECT->value && $recuperateur->get('default_value') !== '') {
            $values = explode("\n", trim($recuperateur->get('select_value'), "\n"));
            $res = [];
            foreach ($values as $key => $value) {
                $explodedValue = explode(':', $value, 2);
                if (count($explodedValue) === 2) {
                    $res[] = $explodedValue[0];
                } else {
                    $res[] = strval($key + 1);
                }
            }
            if (!in_array($recuperateur->get('default_value'), $res, true)) {
                throw new TypeDossierException(
                    'La clé de la valeur par défaut ne correspond à aucune valeur de la liste déroulante'
                );
            }
        }

        $formulaireElement = $this->getFormulaireElementFromProperties($typeDossierData, $element_id);
        if (!$orig_element_id) {
            $typeDossierData->formulaireElement[] = $formulaireElement;
        }
        $typeDossierFormulaireElementManager = new TypeDossierFormulaireElementManager();


        $typeDossierFormulaireElementManager->edition(
            $formulaireElement,
            $recuperateur
        );

        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
    }

    /**
     * @param $id_t
     * @param $element_id
     * @throws Exception
     */
    public function deleteElement($id_t, $element_id)
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);

        $element_index = $this->getFormulaireElementIndex($typeDossierData, $element_id);

        unset($typeDossierData->formulaireElement[$element_index]);
        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
    }

    /**
     * @param $id_t
     * @param $tr
     * @throws Exception
     */
    public function sortElement($id_t, array $tr)
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        $new_form = [];
        foreach ($tr as $element_id) {
            $new_form[] = $this->getFormulaireElementFromProperties($typeDossierData, $element_id);
        }

        if (count($new_form) != count($typeDossierData->formulaireElement)) {
            throw new TypeDossierException("Impossible de retrier le tableau");
        }
        $typeDossierData->formulaireElement = $new_form;
        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
    }

    public function getFieldWithType($id_t, $type)
    {
        $result = [];
        $info = $this->typeDossierManager->getTypeDossierProperties($id_t);
        foreach ($info->formulaireElement as $element_info) {
            if ($element_info->type == $type) {
                $result[$element_info->element_id] = $element_info;
            }
        }
        return $result;
    }

    public function getEtapeInfo($id_t, $num_etape): TypeDossierEtapeProperties
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        if (!isset($typeDossierData->etape[$num_etape])) {
            $result = new TypeDossierEtapeProperties();
            $result->num_etape = 'new';
            return $result;
        }
        return $typeDossierData->etape[$num_etape];
    }


    /**
     * @param $id_t
     * @param Recuperateur $recuperateur
     * @return int
     * @throws Exception
     */
    public function newEtape($id_t, Recuperateur $recuperateur): int
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        $typeDossierEtape = $this->getTypeDossierEtapeFromRecuperateur(
            $recuperateur,
            $recuperateur->get('type')
        );
        $typeDossierData->etape[] = $typeDossierEtape;

        $num_etape = count($typeDossierData->etape) - 1;
        $typeDossierEtape->num_etape = $num_etape ?: 0;
        $typeDossierEtape->defaultChecked = (bool)$recuperateur->get('default_checked', false);

        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
        return $num_etape;
    }

    /**
     * @param $id_t
     * @param Recuperateur $recuperateur
     * @throws Exception
     */
    public function editionEtape($id_t, Recuperateur $recuperateur)
    {
        $num_etape = $recuperateur->get('num_etape') ?: 0;

        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        $type = $typeDossierData->etape[$num_etape]->type;
        $typeDossierEtape = $this->getTypeDossierEtapeFromRecuperateur($recuperateur, $type);
        $typeDossierData->etape[$num_etape] = $typeDossierEtape;
        $typeDossierEtape->type = $type;
        $typeDossierEtape->label = $recuperateur->get('label', null);
        $typeDossierEtape->defaultChecked = (bool)$recuperateur->get('default_checked', false);
        $typeDossierEtape->num_etape = $num_etape ?: 0;
        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
    }

    private function getTypeDossierEtapeFromRecuperateur(Recuperateur $recuperateur, $type): TypeDossierEtapeProperties
    {
        $typeDossierEtape = new TypeDossierEtapeProperties();

        foreach (TypeDossierEtapeManager::getPropertiesId() as $element_formulaire) {
            $typeDossierEtape->$element_formulaire = $recuperateur->get($element_formulaire);
        }

        $configuration_etape = $this->typeDossierEtapeDefinition->getFormulaireConfigurationEtape($type);
        foreach ($configuration_etape as $element_id => $element_info) {
            $typeDossierEtape->specific_type_info[$element_id] = $recuperateur->get($element_id);
        }
        return $typeDossierEtape;
    }

    /**
     * @param $id_t
     * @param $num_etape
     * @throws Exception
     */
    public function deleteEtape($id_t, $num_etape)
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        array_splice($typeDossierData->etape, $num_etape, 1);
        foreach ($typeDossierData->etape as $i => $etape) {
            $typeDossierData->etape[$i]->num_etape = $i;
        }

        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
    }

    /**
     * @param $id_t
     * @param $tr
     * @throws Exception
     */
    public function sortEtape($id_t, $tr)
    {
        $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($id_t);
        $new_cheminement = [];
        foreach ($tr as $num_etape) {
            $new_cheminement[] = $typeDossierData->etape[$num_etape];
        }
        if (count($new_cheminement) != count($typeDossierData->etape)) {
            throw new TypeDossierException("Impossible de retrier le tableau");
        }
        $typeDossierData->etape = $new_cheminement;
        foreach ($typeDossierData->etape as $i => $etape) {
            $typeDossierData->etape[$i]->num_etape = $i;
        }
        $this->typeDossierEditionService->edit($id_t, $typeDossierData);
    }

    private function getEtapeList($typeDossier, $cheminement_list)
    {
        $etapeList = [];
        foreach ($typeDossier->etape as $num_etape => $etape) {
            if (!isset($cheminement_list[$num_etape]) || $cheminement_list[$num_etape]) {
                $etapeList[] = $etape;
            }
        }
        return $etapeList;
    }

    public function getNextActionFromTypeDossier(TypeDossierProperties $typeDossier, string $action_source, array $cheminement_list = []): string
    {
        $etapeList = $this->getEtapeList($typeDossier, $cheminement_list);

        if (in_array($action_source, ['creation', 'modification', 'importation'])) {
            if (!empty($etapeList)) {
                foreach ($this->typeDossierEtapeDefinition->getActionForEtape($etapeList[0]) as $action_name => $action_properties) {
                    return $action_name;
                }
            }
            throw new TypeDossierException("Impossible de trouver la première action à effectuer sur le document");
        }

        $next_etape = null;
        foreach ($etapeList as $num_etape => $etape) {
            $action = $this->typeDossierEtapeDefinition->getActionForEtape($etape);
            foreach ($action as $action_name => $action_info) {
                if ($action_name == $action_source) {
                    if (empty($etapeList[$num_etape + 1])) {
                        return "termine";
                    }
                    $next_etape = $etapeList[$num_etape + 1];
                    break 2;
                }
            }
        }

        $action_list = $this->typeDossierEtapeDefinition->getActionForEtape($next_etape);
        if (!$action_list) {
            throw new TypeDossierException("Aucune action n'a été trouvée");
        }
        return array_keys($action_list)[0];
    }

    /**
     * @param int $id_t
     * @param string $action_source
     * @param array $cheminement_list
     * @return string
     * @throws TypeDossierException
     */
    public function getNextAction(int $id_t, string $action_source, array $cheminement_list = []): string
    {
        $typeDossier = $this->typeDossierManager->getTypeDossierProperties($id_t);
        return $this->getNextActionFromTypeDossier($typeDossier, $action_source, $cheminement_list);
    }

    /**
     * @throws Exception
     */
    public function rebuildAll()
    {
        $all_type_dossier = $this->typeDossierSQL->getAll();
        foreach ($all_type_dossier as $type_dossier_info) {
            $typeDossierData = $this->typeDossierManager->getTypeDossierProperties($type_dossier_info['id_t']);
            $this->typeDossierEditionService->edit($type_dossier_info['id_t'], $typeDossierData);
            $this->pastellLogger->info(
                "Le fichier YAML du flux personnalisé {$typeDossierData->id_type_dossier} a été reconstruit"
            );
        }
    }
}
