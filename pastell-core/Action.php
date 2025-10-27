<?php

//Représente un objet de type action dont les informations
//sont dans un fichier de definition d'un flux à la clé action
// (de premier niveau)
class Action
{
    public const ACTION_DISPLAY_NAME = 'name';
    public const ACTION_DO_DISPLAY_NAME = 'name-action';
    public const ACTION_RULE = 'rule';
    public const ACTION_SCRIPT = 'action-script';
    public const AUTO_SCRIPT = 'auto-script';
    public const ACTION_CLASS = 'action-class';
    public const ACTION_AUTOMATIQUE = 'action-automatique';
    public const ACTION_DESTINATAIRE = 'action-selection';
    public const WARNING = 'warning';
    public const NO_WORKFLOW = 'no-workflow';
    public const EDITABLE_CONTENT = 'editable-content';
    public const PAS_DANS_UN_LOT = 'pas-dans-un-lot';
    public const MODIFICATION_NO_CHANGE_ETAT = 'modification-no-change-etat';
    public const CONNECTEUR_TYPE_DATA_SEDA_CLASS_NAME = 'connecteur-type-data-seda-class-name';
    public const CONNECTEUR_TYPE_MAPPING = 'connecteur-type-mapping';
    public const TRANSFORMATIONS = 'transformations';

    public const ACTION_RULE_LAST_ACTION = 'last-action';
    public const ACTION_RULE_DROIT_ID_U = 'droit_id_u';

    public const CREATION = 'creation';
    public const MODIFICATION = 'modification';

    private $tabAction;

    public function __construct(array $tabAction = [])
    {
        $this->tabAction = $tabAction;
    }

    public function getAll()
    {
        return array_keys($this->tabAction);
    }

    public function getActionName($action_internal_name)
    {
        $tabAction = $this->getActionArray($action_internal_name);
        if (! isset($tabAction[self::ACTION_DISPLAY_NAME])) {
            if ($action_internal_name == 'fatal-error') {
                return "Erreur fatale";
            }

            return $action_internal_name;
        }
        return $tabAction[self::ACTION_DISPLAY_NAME];
    }

    public function getDoActionName($action_internal_name)
    {
        $tabAction = $this->getActionArray($action_internal_name);
        if (! isset($tabAction[self::ACTION_DO_DISPLAY_NAME])) {
            return $this->getActionName($action_internal_name);
        }
        return $tabAction[self::ACTION_DO_DISPLAY_NAME];
    }


    private function getActionArray($action_internal_name)
    {
        if (! isset($this->tabAction[$action_internal_name])) {
            return [];
        }
        return $this->tabAction[$action_internal_name];
    }

    public function getActionRule($action_internal_name)
    {
        $tabAction = $this->getActionArray($action_internal_name);
        if (empty($tabAction[self::ACTION_RULE])) {
            return [];
        }
        return $tabAction[self::ACTION_RULE];
    }

    public function getProperties($action, $properties)
    {
        $tabAction = $this->getActionArray($action);
        if (! isset($tabAction[$properties])) {
            return false;
        }
        return $tabAction[$properties];
    }

    public function getActionScript($action_internal_name)
    {
        $tabAction = $this->getActionArray($action_internal_name);
        if (! isset($tabAction[self::ACTION_SCRIPT])) {
            throw new Exception("L'action $action_internal_name n'est associé à aucun script");
        }
        return $tabAction[self::ACTION_SCRIPT];
    }

    public function getActionClass($action_internal_name)
    {
        $tabAction = $this->getActionArray($action_internal_name);
        if (! isset($tabAction[self::ACTION_CLASS])) {
            return false;
        }
        return $tabAction[self::ACTION_CLASS];
    }

    public function getActionDestinataire($action_internal_name)
    {
        return $this->getProperties($action_internal_name, self::ACTION_DESTINATAIRE);
    }


    public function getAutoAction()
    {
        $result = [];
        foreach ($this->getAll() as $actionName) {
            $autoClass = $this->getProperties($actionName, self::ACTION_AUTOMATIQUE);
            if ($autoClass) {
                $result[$actionName] = $autoClass;
            }
        }
        return $result;
    }

    public function getWarning($action_name)
    {
        return $this->getProperties($action_name, self::WARNING);
    }

    public function getEditableContent($action_name)
    {
        return $this->getProperties($action_name, self::EDITABLE_CONTENT);
    }

    public function getWorkflowAction()
    {
        $result = [];
        foreach ($this->getAll() as $actionName) {
            $no_workflow = $this->getProperties($actionName, self::NO_WORKFLOW);
            if (! $no_workflow) {
                $result[$actionName] = $this->getActionName($actionName);
            }
        }
        return $result;
    }

    public function getActionAutomatique($action)
    {
        return $this->getProperties($action, self::ACTION_AUTOMATIQUE);
    }

    public function getActionWithNotificationPossible()
    {
        $result = [];
        foreach ($this->getWorkflowAction() as $id => $name) {
            $result[] = ['id' => $id,'action_name' => $name];
        }
        return $result;
    }

    public function isPasDansUnLot($action_name)
    {
        return $this->getProperties($action_name, self::PAS_DANS_UN_LOT);
    }

    public function getAllDroit()
    {
        $all_droit = [];
        foreach ($this->tabAction as $action_id => $properties) {
            if (empty($properties['rule'])) {
                continue;
            }
            $all_droit = array_merge($all_droit, $this->getAllDroitRecusif($properties['rule']));
        }
        $all_droit = array_values(array_unique($all_droit));
        return $all_droit;
    }

    private function getAllDroitRecusif($properties)
    {
        $result = [];
        if (isset($properties['droit_id_u'])) {
            $result[] = $properties['droit_id_u'];
        }
        return $result;
    }

    public function getConnecteurTypeDataSedaClassName($action)
    {
        return $this->getProperties($action, self::CONNECTEUR_TYPE_DATA_SEDA_CLASS_NAME);
    }

    public function getConnecteurTypeMapping($action)
    {
        return $this->getProperties($action, self::CONNECTEUR_TYPE_MAPPING) ?: [];
    }

    public function getTransformations(string $action): array
    {
        return $this->getProperties($action, self::TRANSFORMATIONS) ?: [];
    }

    public function getConnecteurMapper($action): StringMapper
    {
        $stringMapper = new StringMapper();
        $stringMapper->setMapping($this->getConnecteurTypeMapping($action));
        return $stringMapper;
    }
}
