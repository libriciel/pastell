<?php

namespace Pastell\Configuration;

use ActionExecutor;
use EntiteSQL;
use Pastell\Viewer\Viewer;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConnectorConfiguration implements ConfigurationInterface
{
    public const NOM = 'nom';
    public const TYPE = 'type';
    public const DESCRIPTION = 'description';
    public const RESTRICTION_PACK = 'restriction_pack';
    public const HERITAGE = 'heritage';
    public const string ALLOW_ON_ENTITE_RACINE = 'allow_on_entite_racine';

    public const FORMULAIRE = 'formulaire';
    public const PAGE_NAME = 'page_name';

    public const ELEMENT_ID = 'element_id';
    public const ELEMENT_NAME = 'name';
    public const ELEMENT_COMMENT = 'commentaire';
    public const ELEMENT_TYPE = 'type';
    public const ELEMENT_VALUE = 'value';
    public const ELEMENT_IS_MULTIPLE = 'multiple';
    public const ELEMENT_DEPEND = 'depend';
    public const ELEMENT_DEFAULT = 'default';
    public const ELEMENT_ONCHANGE = 'onchange';
    public const ELEMENT_NO_SHOW = 'no-show';
    public const ELEMENT_READ_ONLY = 'read-only';
    public const ELEMENT_EDIT_ONLY = 'edit-only';
    public const ELEMENT_CHOICE_ACTION = 'choice-action';
    public const ELEMENT_LINK_NAME = 'link_name';
    public const ELEMENT_CONTENT_TYPE = 'content-type';
    public const ELEMENT_REQUIS = 'requis';
    public const ELEMENT_VISIONNEUSE = 'visionneuse';
    public const ELEMENT_PREG_MATCH = 'preg_match';
    public const ELEMENT_PREG_MATCH_ERROR = 'preg_match_error';

    public const ACTION = 'action';
    public const ACTION_ID = 'action_id';
    public const ACTION_NAME = 'name';
    public const ACTION_CLASS = 'action-class';
    public const ACTION_RULE = 'rule';
    public const ACTION_RULE_USER_PERMISSION = 'droit_id_u';
    public const ACTION_RULE_ENTITY_ROLE = 'role_id_e';
    public const ACTION_RULE_INTERNAL_ACTION = 'internal-action';

    public const ACTION_AUTOMATIQUE = 'action-automatique';
    public const ACTION_CONNECTEUR_TYPE = 'connecteur-type';
    public const ACTION_CONNECTEUR_TYPE_ACTION = 'connecteur-type-action';
    public const ACTION_CONNECTEUR_TYPE_MAPPING = 'connecteur-type-mapping';
    public const ACTION_TRANSFORMATIONS = 'transformations';
    public const ACTION_NO_SHOW = 'no_show';
    public const ACTION_CONNECTEUR_TYPE_ELEMENT_ID = 'element_id';

    public function __construct(
        private readonly \RoleDroit $roleDroit,
    ) {
    }

    /**
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('connector');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode(self::NOM)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Libellé du connecteur')
                ->end()
                ->scalarNode(self::TYPE)
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->info('Famille du connecteur')
                ->end()
                ->scalarNode(self::DESCRIPTION)
                    ->info('Description du connecteur')
                ->end()
                ->scalarNode(self::HERITAGE)
                    ->info('Fichier YML dont hérite le présent fichier')
                ->end()
                ->scalarNode(self::ALLOW_ON_ENTITE_RACINE)
                    ->info('Indique si le connecteur d\'entité peut être créé sur l\'entité racine')
                    ->end()
                ->arrayNode(self::RESTRICTION_PACK)
                    ->info("Indique que le connecteur fait partie d'un des packs")
                    ->scalarPrototype()
                    ->end()
                ->end()
                ->append($this->addFormulaireNode())
                ->append($this->addActionNode())
            ->end()
        ;

        return $treeBuilder;
    }

    private function addFormulaireNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(self::FORMULAIRE);
        $treeBuilder->getRootNode()
            ->useAttributeAsKey(self::PAGE_NAME)
            ->arrayPrototype()
                ->useAttributeAsKey(self::ELEMENT_ID)
                    ->arrayPrototype()
                        ->normalizeKeys(false)
                        ->children()
                            ->scalarNode(self::ELEMENT_NAME)
                            ->end()
                            ->scalarNode(self::ELEMENT_COMMENT)
                            ->end()
                            ->enumNode(self::ELEMENT_TYPE)
                                ->values(array_column(ElementType::cases(), 'value'))
                                ->defaultValue('text')
                            ->end()
                            ->arrayNode(self::ELEMENT_VALUE)
                                ->scalarPrototype()
                                ->end()
                            ->end()
                            ->scalarNode(self::ELEMENT_IS_MULTIPLE)
                            ->end()
                            ->scalarNode(self::ELEMENT_DEPEND)
                                ->setDeprecated('libriciel/pastell', '4.0.0')
                            ->end()
                            ->scalarNode(self::ELEMENT_DEFAULT)
                            ->end()
                            ->scalarNode(self::ELEMENT_ONCHANGE)
                            ->end()
                            ->booleanNode(self::ELEMENT_NO_SHOW)
                            ->end()
                            ->booleanNode(self::ELEMENT_READ_ONLY)
                            ->end()
                            ->booleanNode(self::ELEMENT_EDIT_ONLY)
                            ->end()
                            ->scalarNode(self::ELEMENT_CHOICE_ACTION)
                            ->end()
                            ->scalarNode(self::ELEMENT_LINK_NAME)
                            ->end()
                            ->scalarNode(self::ELEMENT_CONTENT_TYPE)
                            ->end()
                            ->scalarNode(self::ELEMENT_REQUIS)
                            ->end()
                            ->scalarNode(self::ELEMENT_VISIONNEUSE)
                                ->validate()
                                    ->ifTrue(function (string $className) {
                                        return !class_exists($className) ||
                                            !is_subclass_of($className, Viewer::class);
                                    })
                                    ->thenInvalid('Invalid element choice action class %s')
                                ->end()
                            ->end()
                            ->scalarNode(self::ELEMENT_PREG_MATCH)
                                ->validate()
                                    ->ifTrue(function (string $pattern) {
                                        return preg_match($pattern, "") === false;
                                    })
                                    ->thenInvalid('Invalid regexp %s')
                                ->end()
                            ->end()
                            ->scalarNode(self::ELEMENT_PREG_MATCH_ERROR)
                            ->end()
                        ->end()
                    ->end()
                ->end()
        ->end();
        return $treeBuilder->getRootNode();
    }

    private function addActionNode(): NodeDefinition
    {
        $droitList = $this->roleDroit->getAllDroit();
        $treeBuilder = new TreeBuilder(self::ACTION);
        $treeBuilder->getRootNode()
            ->normalizeKeys(false)
            ->useAttributeAsKey(self::ACTION_ID)
                ->arrayPrototype()
                ->normalizeKeys(false)
                    ->children()
                        ->scalarNode(self::ACTION_NAME)
                        ->end()
                        ->scalarNode(self::ACTION_CLASS)
                            ->validate()
                                ->ifTrue(function (string $className) {
                                    return !class_exists($className) ||
                                        !is_subclass_of($className, ActionExecutor::class);
                                })
                                ->thenInvalid('Invalid connecteur action class %s')
                            ->end()
                        ->end()
                        ->arrayNode(self::ACTION_RULE)
                                ->normalizeKeys(false)
                                ->children()
                                    ->scalarNode(self::ACTION_RULE_USER_PERMISSION)
                                        ->validate()
                                            ->ifNotInArray($droitList)
                                            ->thenInvalid('Invalid user permission %s')
                                        ->end()
                                    ->end()
                                    ->scalarNode(self::ACTION_RULE_ENTITY_ROLE)
                                        ->validate()
                                            ->ifNotInArray(EntiteSQL::getAllType())
                                            ->thenInvalid('Invalid entity type %s')
                                        ->end()
                                        ->setDeprecated('libriciel/pastell', '4.0.0')
                                    ->end()
                                    ->booleanNode(self::ACTION_RULE_INTERNAL_ACTION)
                                        ->info(<<<EOT
Permet de créer des actions qui se déclenchent automatiquement sur une mise à jour d'un élément.
L'action n'est pas disponible ni sur la console ni via l'API
EOT)
                                    ->end()
                                ->end()
                        ->end()
                        ->scalarNode(self::ACTION_AUTOMATIQUE)
                        ->end()
                        ->scalarNode(self::ACTION_CONNECTEUR_TYPE)
                        ->end()
                        ->scalarNode(self::ACTION_CONNECTEUR_TYPE_ACTION)
                        ->end()
                        ->booleanNode(self::ACTION_NO_SHOW)
                        ->end()
                        ->arrayNode(self::ACTION_CONNECTEUR_TYPE_MAPPING)
                            ->useAttributeAsKey(self::ACTION_CONNECTEUR_TYPE_ELEMENT_ID)
                            ->scalarPrototype()
                            ->end()
                        ->end()
                        ->arrayNode(self::ACTION_TRANSFORMATIONS)
                            ->useAttributeAsKey(self::ACTION_CONNECTEUR_TYPE_ELEMENT_ID)
                            ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder->getRootNode();
    }
}
