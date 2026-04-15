<?php

class TypeDossierFormulaireElementManager
{
    //Bon, c'est pas là que ca devrait être défini, mais c'est forcé par la taille du champs indexable
    public const ELEMENT_ID_MAX_LENGTH = 64;

    //Même remarque
    public const ELEMENT_ID_REGEXP = "^[a-z_][0-9a-z_]*$";

    public const ELEMENT_ID = "element_id";
    public const NAME = "name";
    public const TYPE = "type";
    public const COMMENTAIRE = "commentaire";
    public const REQUIS = "requis";
    public const CHAMPS_AFFICHES = "champs_affiches";
    public const CHAMPS_RECHERCHE_AVANCEE = "champs_recherche_avancee";
    public const TITRE = "titre";
    public const SELECT_VALUE = "select_value";
    public const PREG_MATCH = 'preg_match';
    public const PREG_MATCH_ERROR = 'preg_match_error';
    public const DEFAULT_VALUE = 'default_value';
    public const CONTENT_TYPE = 'content_type';

    public const TYPE_TEXT = "text";
    public const TYPE_FILE = "file";
    public const TYPE_MULTI_FILE = "multi_file";
    public const TYPE_TEXTAREA = "textarea";
    public const TYPE_PASSWORD = "password";
    public const TYPE_CHECKBOX = "checkbox";
    public const TYPE_DATE = "date";
    public const TYPE_SELECT = "select";

    public static function getElementPropertiesId()
    {
        return [
            self::ELEMENT_ID,
            self::NAME,
            self::TYPE,
            self::COMMENTAIRE,
            self::REQUIS,
            self::CHAMPS_AFFICHES,
            self::CHAMPS_RECHERCHE_AVANCEE,
            self::TITRE,
            self::SELECT_VALUE,
            self::PREG_MATCH,
            self::DEFAULT_VALUE,
            self::PREG_MATCH_ERROR,
            self::CONTENT_TYPE
        ];
    }

    public static function getAllTypeElement()
    {
        return [
            self::TYPE_TEXT => 'Texte (une ligne)',
            self::TYPE_FILE => 'Fichier',
            self::TYPE_MULTI_FILE => 'Fichier(s) multiple(s)',
            self::TYPE_TEXTAREA => 'Zone de texte (multi-ligne)',
            self::TYPE_PASSWORD => 'Mot de passe',
            self::TYPE_CHECKBOX => 'Case à cocher',
            self::TYPE_DATE => 'Date',
            self::TYPE_SELECT => 'Liste déroulante'
        ];
    }

    public static function getTypeElementLibelle($id)
    {
        $all_type = self::getAllTypeElement();
        if (! isset($all_type[$id])) {
            return false;
        }
        return self::getAllTypeElement()[$id];
    }

    public function getElementFromArray(array $properties)
    {
        $newFormElement = new TypeDossierFormulaireElementProperties();

        foreach (self::getElementPropertiesId() as $key) {
            if (isset($properties[$key])) {
                $newFormElement->$key = $properties[$key];
            } else {
                $newFormElement->$key = false;
            }
        }
        return $newFormElement;
    }

    /**
     * @param TypeDossierFormulaireElementProperties $typeDossierFormulaireElement
     * @param Recuperateur $recuperateur
     * @return bool
     * @throws TypeDossierException
     */
    public function edition(
        TypeDossierFormulaireElementProperties $typeDossierFormulaireElement,
        Recuperateur $recuperateur
    ) {
        $this->verifElementId($recuperateur->get(self::ELEMENT_ID));
        $this->verifType($recuperateur->get(self::TYPE));
        foreach (self::getElementPropertiesId() as $element_formulaire) {
            $typeDossierFormulaireElement->$element_formulaire = $recuperateur->get($element_formulaire);
        }
        if (! $typeDossierFormulaireElement->name) {
            $typeDossierFormulaireElement->name = $typeDossierFormulaireElement->element_id;
        }
        return true;
    }

    /**
     * @param $element_id
     * @throws TypeDossierException
     */
    private function verifElementId($element_id): void
    {
        if (! $element_id) {
            throw new TypeDossierException("L'identifiant ne peut être vide");
        }
        if (strlen($element_id) > self::ELEMENT_ID_MAX_LENGTH) {
            throw new TypeDossierException("La longueur de l'identifiant ne peut dépasser 64 caractères");
        }
        if (preg_match("#^\d#", $element_id)) {
            throw new TypeDossierException("L'identifiant de l'élément ne doit pas commencer par un chiffre");
        }
        if (! preg_match("#" . self::ELEMENT_ID_REGEXP . "#", $element_id)) {
            throw new TypeDossierException(
                "L'identifiant de l'élément ne peut comporter que des chiffres, des lettres minuscules et le caractère _"
            );
        }
    }

    /**
     * @param $type
     * @throws TypeDossierException
     */
    private function verifType($type)
    {
        if (! self::getTypeElementLibelle($type)) {
            throw new TypeDossierException(
                "Le type n'existe pas"
            );
        }
    }
}
