<?php

class ActionPossible
{
    public const FATAL_ERROR_ACTION = 'fatal-error';

    private $lastBadRule;

    private $documentActionEntite;
    private $documentEntite;
    /** @var RoleUtilisateur  */
    private $roleUtilisateur;
    /** @var DocumentTypeFactory */
    private $documentTypeFactory;
    private DocumentSQL $document;
    private $entiteSQL;
    private $donneesFormulaireFactory;
    private $connecteurEntiteSQL;


    /* Ces élements sont calculé à chaque appel d'une fonction publique */
    private $last_action;
    private $role_entite;
    private $action_list;
    private $utilisateur_droit_list;
    private $connecteur_entite_info;
    private ?DonneesFormulaire $donneesFormulaire;
    private $entite_info;
    /** @var DocumentType */
    private $documentType;
    /** @var Action */
    private $actionObject;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->document = $objectInstancier->getInstance(DocumentSQL::class);
        $this->documentActionEntite = $objectInstancier->getInstance(DocumentActionEntite::class);
        $this->documentEntite = $objectInstancier->getInstance(DocumentEntite::class);
        $this->roleUtilisateur = $objectInstancier->getInstance(RoleUtilisateur::class);
        $this->entiteSQL = $objectInstancier->getInstance(EntiteSQL::class);
        $this->documentTypeFactory = $objectInstancier->getInstance(DocumentTypeFactory::class);
        $this->donneesFormulaireFactory = $objectInstancier->getInstance(DonneesFormulaireFactory::class);
        $this->connecteurEntiteSQL = $objectInstancier->getInstance(ConnecteurEntiteSQL::class);
    }

    public function getLastBadRule()
    {
        return $this->lastBadRule;
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @param $action_name
     * @return bool
     * @throws Exception
     */
    public function isActionPossible($id_e, $id_u, $id_d, $action_name)
    {
        $this->buildCache($id_e, $id_u, $id_d);
        return $this->isActionPossibleWithCache($id_u, $id_d, $action_name);
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $type_document
     * @return bool
     * @throws Exception
     */
    public function isCreationPossible($id_e, $id_u, $type_document)
    {
        $this->buildCache($id_e, $id_u, false, $type_document);
        return $this->isCreationPossibleWithCache($id_e, $id_u);
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @return array
     * @throws Exception
     */
    public function getActionPossible($id_e, $id_u, $id_d)
    {
        $this->buildCache($id_e, $id_u, $id_d);
        return $this->getActionPossibleWithCache($id_u, $id_d);
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @return array
     * @throws Exception
     */
    public function getActionPossibleLot($id_e, $id_u, $id_d)
    {
        $this->buildCache($id_e, $id_u, $id_d);
        return $this->getActionPossibleLotWithCache($id_u, $id_d);
    }

    /**
     * @param $id_ce
     * @param $id_u
     * @return array
     * @throws Exception
     */
    public function getActionPossibleOnConnecteur($id_ce, $id_u)
    {
        $this->buildCacheForConnecteur($id_ce, $id_u);
        return $this->getActionPossibleOnConnecteurWithCache($id_u);
    }

    /**
     * @param $id_ce
     * @param $id_u
     * @param $action_name
     * @return bool
     * @throws Exception
     */
    public function isActionPossibleOnConnecteur($id_ce, $id_u, $action_name)
    {
        $this->buildCacheForConnecteur($id_ce, $id_u);
        return $this->isActionPossibleOnConnecteurWithCache($id_u, $action_name);
    }

    /**
     * @param $id_e
     * @param $id_u
     * @param $id_d
     * @param bool $type_document
     * @throws Exception
     */
    private function buildCache($id_e, $id_u, $id_d, $type_document = false)
    {
        $this->last_action = $this->documentActionEntite->getLastAction($id_e, $id_d);
        $this->role_entite = $this->documentEntite->getRole($id_e, $id_d);
        $this->action_list = array_map(
            function ($a) {
                return $a['action'];
            },
            $this->documentActionEntite->getAction($id_e, $id_d)
        );
        $this->connecteur_entite_info = false;
        $this->utilisateur_droit_list = $this->roleUtilisateur->getAllDroitEntite($id_u, $id_e);

        $this->donneesFormulaire = $this->donneesFormulaireFactory->get($id_d, $type_document);
        $this->entite_info = $this->entiteSQL->getInfo($id_e);

        if (! $type_document) {
            $type_document = $this->getTypeDocument($id_d);
        }

        $this->documentType = $this->getDocumentType($type_document);
        $this->actionObject = $this->documentType->getAction();
    }

    /**
     * @param $id_ce
     * @param $id_u
     * @throws Exception
     */
    private function buildCacheForConnecteur($id_ce, $id_u)
    {
        $this->last_action = false;
        $this->role_entite = false;
        $this->action_list = [];
        $this->connecteur_entite_info = $this->connecteurEntiteSQL->getInfo($id_ce);

        $this->utilisateur_droit_list = $this->roleUtilisateur->getAllDroitEntite($id_u, $this->connecteur_entite_info['id_e']);
        $this->donneesFormulaire = null;
        $this->entite_info = $this->entiteSQL->getInfo($this->connecteur_entite_info['id_e']);
        $this->documentType = ($this->connecteur_entite_info['global']) ?
            $this->documentTypeFactory->getGlobalDocumentType($this->connecteur_entite_info['id_connecteur'])
            : $this->documentTypeFactory->getEntiteDocumentType($this->connecteur_entite_info['id_connecteur']);
        $this->actionObject = $this->documentType->getAction();
    }

    /**
     * @param $id_u
     * @param $id_d
     * @param $action_name
     * @return bool
     * @throws Exception
     */
    private function isActionPossibleWithCache($id_u, $id_d, $action_name)
    {
        $type_document = $this->getTypeDocument($id_d);

        if ($action_name == self::FATAL_ERROR_ACTION) {
            return $this->verifDroitUtilisateur($id_u, "$type_document:edition");
        }
        return $this->internIsActionPossible($id_u, $action_name);
    }

    /**
     * @param $id_e
     * @param $id_u
     * @return bool
     * @throws Exception
     */
    private function isCreationPossibleWithCache($id_e, $id_u)
    {
        if (! $id_e) {
            return false;
        }

        if (! $this->entite_info['is_active']) {
            return false;
        }

        return $this->internIsActionPossible($id_u, Action::CREATION);
    }

    /**
     * @param $id_u
     * @param $id_d
     * @return array
     * @throws Exception
     */
    private function getActionPossibleWithCache($id_u, $id_d)
    {
        $possible = [];

        foreach ($this->actionObject->getAll() as $action_name) {
            if ($this->isActionPossibleWithCache($id_u, $id_d, $action_name)) {
                $possible[] = $action_name;
            }
        }

        return $possible;
    }

    /**
     * @param $id_u
     * @param $id_d
     * @return array
     * @throws Exception
     */
    private function getActionPossibleLotWithCache($id_u, $id_d)
    {
        $action_possible_list = $this->getActionPossibleWithCache($id_u, $id_d);

        $result = [];
        foreach ($action_possible_list as $action_possible) {
            if ($action_possible == 'modification') {
                continue;
            }
            if ($this->actionObject->isPasDansUnLot($action_possible)) {
                continue;
            }
            $result[] = $action_possible;
        }
        return $result;
    }


    /**
     * @param $id_u
     * @return array
     * @throws Exception
     */
    private function getActionPossibleOnConnecteurWithCache($id_u)
    {
        $possible = [];
        foreach ($this->actionObject->getAll() as $action_name) {
            if ($this->isActionPossibleOnConnecteurWithCache($id_u, $action_name)) {
                $possible[] = $action_name;
            }
        }
        return $possible;
    }

    /**
     *
     * @param $id_u
     * @param $action_name
     * @return bool
     * @throws Exception
     */
    private function isActionPossibleOnConnecteurWithCache($id_u, $action_name)
    {
        return $this->internIsActionPossible($id_u, $action_name);
    }

    private function getDocumentType($type_document)
    {
        return $this->documentTypeFactory->getFluxDocumentType($type_document);
    }

    private function getTypeDocument($id_d)
    {
        $infoDocument = $this->document->getInfo($id_d);
        return $infoDocument['type'];
    }

    /**
     * @param $id_u
     * @param $action_name
     * @return bool
     * @throws InternalServerException
     */
    private function internIsActionPossible($id_u, $action_name)
    {

        $action_rule = $this->actionObject->getActionRule($action_name);

        foreach ($action_rule as $ruleName => $ruleValue) {
            if (! $this->verifRule($id_u, $ruleName, $ruleValue)) {
                if ($ruleName == "last-action") {
                    $this->lastBadRule = "Le dernier état du document ($this->last_action) ne permet pas de déclencher cette action";
                } else {
                    $this->lastBadRule = "$ruleName n'est pas vérifiée";
                }
                return false;
            }
        }

        return true;
    }

    /**
     * @param $id_u
     * @param $ruleName
     * @param $ruleValue
     * @return bool
     * @throws InternalServerException
     */
    private function verifRule($id_u, $ruleName, $ruleValue)
    {

        if (!strncmp($ruleName, 'and', 3)) {
            foreach ($ruleValue as $ruleName => $ruleElement) {
                if (! $this->verifRule($id_u, $ruleName, $ruleElement)) {
                    return false;
                }
            }
            return true;
        }

        if (!strncmp($ruleName, 'or', 2)) {
            foreach ($ruleValue as $ruleName => $ruleElement) {
                if ($this->verifRule($id_u, $ruleName, $ruleElement)) {
                    return true;
                }
            }
            return false;
        }
        if (!strncmp($ruleName, 'no_', 3)) {
            foreach ($ruleValue as $ruleName => $ruleElement) {
                if ($this->verifRule($id_u, $ruleName, $ruleElement)) {
                    return false;
                }
            }
            return true;
        }
        if (is_array($ruleValue) && ! in_array($ruleName, ['collectivite-properties','herited-properties','content','properties'])) {
            foreach ($ruleValue as $ruleElement) {
                if ($this->verifRule($id_u, $ruleName, $ruleElement)) {
                    return true;
                }
            }
            return false;
        }

        switch ($ruleName) {
            case 'no-last-action':
                return $this->verifLastAction(false);
            case 'last-action':
                return $this->verifLastAction($ruleValue);
            case 'has-action':
                return ! $this->verifNoAction($ruleValue);
            case 'no-action':
                return $this->verifNoAction($ruleValue);
            case 'role_id_e':
                return $this->verifRoleEntite($ruleValue);
            case 'droit_id_u':
                return $this->verifDroitUtilisateur($id_u, $ruleValue);
            case 'content':
                return $this->verifContent($ruleValue);
            case 'type_id_e':
                return $this->veriTypeEntite($ruleValue);
            case 'document_is_valide':
                return $this->verifDocumentIsValide();
            case 'internal-action':
                return false;
        }
        throw new InternalServerException("Règle d'action inconnue : $ruleName");
    }

    private function verifLastAction($last_action)
    {
        return $last_action == $this->last_action;
    }

    private function verifNoAction($value)
    {
        return ! in_array($value, $this->action_list);
    }

    private function verifRoleEntite($value)
    {
        return $this->role_entite == $value ;
    }

    private function verifDroitUtilisateur($id_u, $value)
    {
        if ($id_u === 0) {
            return true;
        }
        return in_array($value, $this->utilisateur_droit_list);
    }


    private function verifContent($value)
    {
        foreach ($value as $fieldName => $fieldValue) {
            if (! $this->verifField($fieldName, $fieldValue)) {
                return false;
            }
        }
        return true;
    }

    private function verifDocumentIsValide()
    {
        return $this->donneesFormulaire->isValidable();
    }

    private function verifField($fieldName, $fieldValue)
    {
        return $this->donneesFormulaire->get($fieldName) == $fieldValue;
    }

    private function veriTypeEntite($type)
    {
        return ($this->entite_info["type"] == $type);
    }
}
