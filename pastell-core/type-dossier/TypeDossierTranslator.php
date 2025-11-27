<?php

class TypeDossierTranslator
{
    public const ORIENTATION = 'orientation';

    public function __construct(
        private readonly YMLLoader $ymlLoader,
        private readonly TypeDossierEtapeManager $typeDossierEtapeDefinition,
        private readonly string $data_dir,
    ) {
    }

    /**
     * Construit le YAML d'un type de dossier a partir d'un TypeDossierData
     * @param TypeDossierProperties $typeDossierData
     * @return array
     */
    public function getDefinition(TypeDossierProperties $typeDossierData)
    {
        $result = $this->setStarter($typeDossierData);
        $this->setFormulaireElement($typeDossierData, $result);
        $this->setOngletCheminement($typeDossierData, $result);
        $this->setOngletForEtapeList($typeDossierData, $result);
        $this->setPageCondition($typeDossierData, $result);
        $this->setConnecteur($typeDossierData, $result);
        $this->setAction($typeDossierData, $result);

        //VERY HUGLY HACK
        $result['__temporary_id'] = $typeDossierData->id_type_dossier;
        $this->setSpecific($typeDossierData, $result);
        unset($result['__temporary_id']);

        return $result;
    }

    private function setStarter(TypeDossierProperties $typeDossierData)
    {
        $result = $this->ymlLoader->getArray($this->data_dir . '/type-dossier-starter-kit.yml');
        $result[DocumentType::NOM] = $typeDossierData->nom;
        $result[DocumentType::TYPE_FLUX] = $typeDossierData->type;
        $result[DocumentType::DESCRIPTION] = $typeDossierData->description;
        $result[DocumentType::FORMULAIRE] = [];
        $result[DocumentType::PAGE_CONDITION] = $result[DocumentType::PAGE_CONDITION] ?: [];
        $result[DocumentType::CONNECTEUR] = $result[DocumentType::CONNECTEUR] ?: [];
        $result[DocumentType::AFFICHE_ONE] = $typeDossierData->affiche_one;
        if ($typeDossierData->restriction_pack) {
            $result[DocumentType::RESTRICTION_PACK][] = $typeDossierData->restriction_pack;
        }
        return $result;
    }

    private function setFormulaireElement(TypeDossierProperties $typeDossierData, array &$result)
    {
        $onglet_name = $typeDossierData->nom_onglet ?: 'onglet1';
        foreach ($typeDossierData->formulaireElement as $typeDossierFormulaireElement) {
            $element_id = $typeDossierFormulaireElement->element_id;
            $result[DocumentType::FORMULAIRE][$onglet_name][$element_id] = [
                'name' => $typeDossierFormulaireElement->name ?: $element_id,
                'type' => $this->getType($typeDossierFormulaireElement),
                Field::REQUIS => (bool)$typeDossierFormulaireElement->requis,
                'multiple' => $typeDossierFormulaireElement->type === 'multi_file',
                'commentaire' => $typeDossierFormulaireElement->commentaire,
            ];

            if (
                $typeDossierFormulaireElement->default_value !== false &&
                $typeDossierFormulaireElement->default_value !== '' &&
                \in_array(
                    $typeDossierFormulaireElement->type,
                    [
                        TypeDossierFormulaireElementManager::TYPE_TEXT,
                        TypeDossierFormulaireElementManager::TYPE_TEXTAREA,
                        TypeDossierFormulaireElementManager::TYPE_CHECKBOX,
                        TypeDossierFormulaireElementManager::TYPE_SELECT,
                        TypeDossierFormulaireElementManager::TYPE_DATE,
                    ],
                    true
                )
            ) {
                $defaultValue = $typeDossierFormulaireElement->default_value;
                if ($typeDossierFormulaireElement->type === TypeDossierFormulaireElementManager::TYPE_DATE) {
                    $defaultValue = 'now';
                }
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id]['default']
                    = $defaultValue;
            }

            if ($typeDossierFormulaireElement->type == TypeDossierFormulaireElementManager::TYPE_SELECT) {
                $values = explode("\n", trim($typeDossierFormulaireElement->select_value, "\n"));
                $res = [];
                foreach ($values as $key => $value) {
                    $explodedValue = explode(':', trim($value), 2);
                    if (count($explodedValue) === 2) {
                        $res[$explodedValue[0]] = $explodedValue[1];
                    } else {
                        $res[$key + 1] = $explodedValue[0];
                    }
                }
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id]['value'] = $res;
            }
            if (
                $typeDossierFormulaireElement->preg_match
                && in_array(
                    $this->getType($typeDossierFormulaireElement),
                    [
                        TypeDossierFormulaireElementManager::TYPE_TEXT,
                        TypeDossierFormulaireElementManager::TYPE_TEXTAREA
                    ],
                    true
                )
            ) {
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id][TypeDossierFormulaireElementManager::PREG_MATCH] = $typeDossierFormulaireElement->preg_match;
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id][TypeDossierFormulaireElementManager::PREG_MATCH_ERROR] = $typeDossierFormulaireElement->preg_match_error;
            }
            if ($typeDossierFormulaireElement->content_type) {
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id]['content-type'] = $typeDossierFormulaireElement->content_type;
            }
            if ($typeDossierFormulaireElement->titre) {
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id]['title'] = true;
            }
            if ($typeDossierFormulaireElement->champs_recherche_avancee || $typeDossierFormulaireElement->champs_affiches) {
                $result[DocumentType::FORMULAIRE][$onglet_name][$element_id]['index'] = true;
            }
            if ($typeDossierFormulaireElement->champs_affiches) {
                $result['champs-affiches'][] = $element_id;
            }
            if ($typeDossierFormulaireElement->champs_recherche_avancee) {
                $result['champs-recherche-avancee'][] = $element_id;
            }
        }
    }

    private function setOngletCheminement(TypeDossierProperties $typeDossierData, array &$result)
    {
        $cheminement = [];
        foreach ($typeDossierData->etape as $typeDossierEtape) {
            $cheminement[] = $typeDossierEtape;
        }
        foreach ($cheminement as $typeDossierEtape) {
            $element_id = $this->getEnvoiTypeElementId($typeDossierEtape);
            $result[DocumentType::FORMULAIRE]['Cheminement'][$element_id] =
                [
                    'name' => $typeDossierEtape->label ?: $this->getEnvoiTypeLibelle($typeDossierEtape),
                    'type' => 'checkbox',
                    'onchange' => 'cheminement-change',
                    'default' => ($typeDossierEtape->requis || $typeDossierEtape->defaultChecked) ? "checked" : "",
                    'read-only' => boolval($typeDossierEtape->requis)
                ];
        }
    }

    private function getEnvoiTypeElementId(TypeDossierEtapeProperties $typeDossierEtape): string
    {
        $result = "envoi_{$typeDossierEtape->type}";
        if (!$typeDossierEtape->etape_with_same_type_exists) {
            return $result;
        }

        return sprintf("%s_%d", $result, $typeDossierEtape->num_etape_same_type + 1);
    }

    private function getEnvoiTypeLibelle(TypeDossierEtapeProperties $typeDossierEtape): string
    {
        $all_type = $this->typeDossierEtapeDefinition->getAllType();
        if (empty($all_type[$typeDossierEtape->type])) {
            return "";
        }
        $result = $all_type[$typeDossierEtape->type];
        if (!$typeDossierEtape->etape_with_same_type_exists) {
            return $result;
        }

        return sprintf("%s #%d", $result, $typeDossierEtape->num_etape_same_type + 1);
    }

    private function setOngletForEtapeList(TypeDossierProperties $typeDossierData, array &$result)
    {
        $onglet1_element_ids = $result[DocumentType::FORMULAIRE][$typeDossierData->nom_onglet] ?? [];
        foreach ($typeDossierData->etape as $etape) {
            foreach ($this->typeDossierEtapeDefinition->getFormulaireForEtape($etape) as $onglet_name => $onglet_content) {
                $onglet_content = array_diff_key($onglet_content, $onglet1_element_ids);
                $result[DocumentType::FORMULAIRE][$onglet_name] = $onglet_content;
            }
        }
    }

    private function getElementIdList($result)
    {
        $element_id_list = [];
        foreach ($result[DocumentType::FORMULAIRE] as $element_list) {
            foreach ($element_list as $element_id => $element_properties) {
                $element_id_list[] = $element_id;
            }
        }
        return $element_id_list;
    }

    private function setPageCondition(TypeDossierProperties $typeDossierData, array &$result)
    {
        $element_id_list = $this->getElementIdList($result);
        foreach ($typeDossierData->etape as $etape) {
            foreach ($this->typeDossierEtapeDefinition->getPageCondition($etape) as $onglet_name => $onglet_condition) {
                foreach ($onglet_condition as $element_id => $element_value) {
                    if (in_array($element_id, $element_id_list)) {
                        $result[DocumentType::PAGE_CONDITION][$onglet_name] = $onglet_condition;
                    }
                }
            }
        }

        if (!$result[DocumentType::PAGE_CONDITION]) {
            unset($result[DocumentType::PAGE_CONDITION]);
        }
    }

    private function setConnecteur(TypeDossierProperties $typeDossierData, array &$result)
    {
        foreach ($typeDossierData->etape as $etape) {
            $result['connecteur'] = array_merge($result['connecteur'], $this->typeDossierEtapeDefinition->getConnecteurType($etape->type));
        }
    }

    private function setAction(TypeDossierProperties $typeDossierData, array &$result)
    {
        $this->setBaseAction($typeDossierData, $result);
        $this->setActionAutomatique($typeDossierData, $result);
    }

    private function setBaseAction(TypeDossierProperties $typeDossierData, array &$result)
    {
        foreach ($typeDossierData->etape as $etape) {
            $action_list = $this->typeDossierEtapeDefinition->getActionForEtape($etape);

            foreach ($action_list as $action_id => $action_properties) {
                $result[DocumentType::ACTION][$action_id] = $action_properties;
                if ($etape->etape_with_same_type_exists) {
                    $result[DocumentType::ACTION][$action_id]['num-same-connecteur'] = strval($etape->num_etape_same_type);
                }
            }
        }
    }

    private function setActionAutomatique(TypeDossierProperties $typeDossierData, array &$result)
    {
        $cheminementElementIdList = [];
        $ongletElementId = [];

        foreach ($typeDossierData->etape as $typeDossierEtape) {
            $cheminementElementId = $this->getEnvoiTypeElementId($typeDossierEtape);
            $cheminementElementIdList[] = $cheminementElementId;
            foreach ($this->typeDossierEtapeDefinition->getFormulaireForEtape($typeDossierEtape) as $onglet_content) {
                foreach ($onglet_content as $elementId => $item) {
                    if (empty($item['read-only'])) {
                        $ongletElementId[$cheminementElementId][] = $elementId;
                    }
                }
            }
        }
        foreach ($typeDossierData->etape as $etape) {
            array_shift($cheminementElementIdList);
            foreach ($this->typeDossierEtapeDefinition->getActionForEtape($etape) as $action_id => $action_properties) {
                if (isset($action_properties[Action::ACTION_AUTOMATIQUE]) && $action_properties[Action::ACTION_AUTOMATIQUE] == self::ORIENTATION) {
                    $result[DocumentType::ACTION][self::ORIENTATION][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][] = $action_id;
                    if (!$etape->automatique) {
                        unset($result[DocumentType::ACTION][$action_id][Action::ACTION_AUTOMATIQUE]);
                    }
                    if ($cheminementElementIdList) {
                        $result[DocumentType::ACTION][ModificationAction::ACTION_ID][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][] = $action_id;
                        $result[DocumentType::ACTION][$action_id][Action::EDITABLE_CONTENT] = $cheminementElementIdList;
                        foreach ($cheminementElementIdList as $elementId) {
                            if (! empty($ongletElementId[$elementId])) {
                                $result[DocumentType::ACTION][$action_id][Action::EDITABLE_CONTENT] = array_merge(
                                    $result[DocumentType::ACTION][$action_id][Action::EDITABLE_CONTENT],
                                    $ongletElementId[$elementId]
                                );
                            }
                        }
                        $result[DocumentType::ACTION][$action_id][Action::MODIFICATION_NO_CHANGE_ETAT] = true;
                    }
                }
            }
        }
    }

    private function setSpecific(TypeDossierProperties $typeDossierData, array &$result)
    {
        foreach ($typeDossierData->etape as $etape) {
            $result = $this->typeDossierEtapeDefinition->setSpecificData($etape, $result);
        }
    }

    private function getType(TypeDossierFormulaireElementProperties $typeDossierFormulaireElement)
    {
        if ($typeDossierFormulaireElement->type == 'multi_file') {
            return 'file';
        }
        return $typeDossierFormulaireElement->type;
    }
}
