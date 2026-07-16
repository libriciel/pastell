<?php

declare(strict_types=1);

namespace Pastell\Configuration;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class DocumentTypeConfiguration implements ConfigurationInterface
{
    public const MODULE = 'module';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::MODULE);
        $treeBuilder->getRootNode()
            ->info("Le fichier definition.yml contient la définition d'un module Pastell")
            ->normalizeKeys(false)
            ->children()
                ->scalarNode(ModuleElement::NOM->value)
                    ->info("Identifiant du module tel qu'il sera présenté aux utilisateurs.")
                ->end()
                ->scalarNode(SearchField::TYPE->value)
                    ->info('Type de module. Utiliser pour classer les modules les uns par rapport aux autres.')
                    ->defaultValue('Types de dossier génériques')
                ->end()
                ->scalarNode(ModuleElement::DESCRIPTION->value)
                    ->info('Permet de décrire le module')
                ->end()
                ->arrayNode(ModuleElement::RESTRICTION_PACK->value)
                    ->info("Liste des restrictions d'utilisation pack (pack_chorus_pro, pack_marche...)")
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode(ModuleElement::CONNECTEUR->value)
                    ->info('Liste des types de connecteur utilisés par le module')
                    ->scalarPrototype()->end()
                ->end()
                ->append($this->addFormulaireNode())
                ->append($this->addPageConditionNode())
                ->append($this->addActionNode())
                ->booleanNode(ModuleElement::AFFICHE_ONE->value)
                    ->info("Permet d'afficher l'ensemble des onglets sur une seule page "
                            . '(en mode affichage, les onglets sont conservés en mode édition)')
                    ->defaultFalse()
                ->end()
                ->arrayNode(ModuleElement::CHAMPS_AFFICHES->value)
                    ->info('Liste des champs à afficher dans la liste des dossiers')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode(ModuleElement::CHAMPS_RECHERCHE_AVANCEE->value)
                    ->info('Liste des champs à afficher dans la recherche avancée')
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode(ModuleElement::THRESHOLD_SIZE->value)
                    ->info('Taille limite acceptée de tous les fichiers du dossier cumulés en octet. '
                            . '(optionnel, sans limite par défaut)')
                ->end()
                ->arrayNode(ModuleElement::THRESHOLD_FIELDS->value)
                    ->info('Liste des champs de type file utilisés dans le calcul à la limite '
                            . 'définie par la clé threshold_size (optionnel, tous les champs `file` par défaut)')
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode(ModuleElement::STUDIO_DEFINITION->value)
                    ->info('Permet de préciser le contenu original du flux studio qui a permis la création '
                            . 'de ce type de dossier (en JSON)')
                ->end()
            ->end();
        return $treeBuilder;
    }

    private function addFormulaireNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(ModuleElement::FORMULAIRE->value);
        $treeBuilder->getRootNode()
            ->info("Définition du formulaire permettant la création et l'évolution du dossier")
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->info("le formulaire est composé d'un ensemble d'onglets")
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->info("un onglet est composé d'éléments de formulaire")
                    ->normalizeKeys(false)
                    ->children()
                        ->scalarNode(FormulaireElement::NAME->value)->end()
                        ->enumNode(SearchField::TYPE->value)
                            ->values(array_column(ElementType::cases(), 'value'))
                        ->end()
                        ->scalarNode(FormulaireElement::LINK_NAME->value)
                            ->info('Uniquement pour le type externalData, afin de mettre un texte sur le lien')
                        ->end()
                        ->scalarNode(FormulaireElement::DEFAULT->value)
                            ->info('Valeur initiale prise par le champs lors de la création du document.'
                                    . "\nPour une date, peut prendre en compte les chaînes de caractères "
                                    . 'compatibles avec la fonction PHP strtotime()'
                                    . "\n(Exemple: -30days), peut prendre la valeur \"empty\" "
                                    . 'pour spécifier de ne pas renseigner la date (sinon now par défaut)')
                        ->end()
                        ->booleanNode(FormulaireElement::INDEX->value)
                            ->info('Indique si le champs est indexé par la base de données')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode(FormulaireElement::REQUIS->value)->end()
                        ->scalarNode(FormulaireElement::PREG_MATCH->value)->end()
                        ->scalarNode(FormulaireElement::PREG_MATCH_ERROR->value)->end()
                        ->scalarNode(FormulaireElement::COMMENTAIRE->value)
                            ->info('Le commentaire est affiché comme aide en mode édition')
                        ->end()
                        ->booleanNode(FormulaireElement::TITLE->value)
                            ->info('Information enregistré dans la base de donnée pour identifier le document')
                        ->end()
                        ->booleanNode(FormulaireElement::MULTIPLE->value)
                            ->info('uniquement pour le type file')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode(FormulaireElement::VISIONNEUSE->value)
                            ->info('Permet de spécifier une classe utilisé pour visualiser le ou les fichiers')
                        ->end()
                        ->booleanNode(FormulaireElement::VISIONNEUSE_NO_LINK->value)
                            ->info("Le lien pour télécharger le fichier n'est pas affiché\n"
                                    . "Ne fait rien si la propriété visionneuse n'est pas utilisée.")
                        ->end()
                        ->scalarNode(FormulaireElement::CHOICE_ACTION->value)
                            ->info('Pointeur vers une action')
                        ->end()
                        ->scalarNode(FormulaireElement::ONCHANGE->value)->end()
                        ->booleanNode(FormulaireElement::READ_ONLY->value)
                            ->info('Le champ ne sera pas affiché en mode édition, mais seulement en mode affichage')
                        ->end()
                        ->booleanNode(FormulaireElement::EDIT_ONLY->value)
                            ->info('Le champ ne sera pas affiché en mode affichage, mais seulement en mode édition')
                        ->end()
                        ->scalarNode(FormulaireElement::AUTOCOMPLETE->value)->end()
                        ->booleanNode(FormulaireElement::MAY_BE_NULL->value)->end()
                        ->scalarNode(FormulaireElement::IS_EQUAL->value)->end()
                        ->scalarNode(FormulaireElement::IS_EQUAL_ERROR->value)->end()
                        ->scalarNode(FormulaireElement::DEPEND->value)
                            ->info("champs multiple dépendant d'un champ de type file (multiple)")
                        ->end()
                        ->booleanNode(FormulaireElement::NO_SHOW->value)
                            ->info("Le champs ne sera pas affiché (ni en mode affichage, ni en mode d'édition)")
                        ->end()
                        ->scalarNode(FormulaireElement::CONTENT_TYPE->value)
                            ->info("Permet de spécifier une liste de content-type séparé par une virgule.\n"
                                . "Ne fonctionne que pour le type fichier.\n"
                                . 'Indique que le ou les fichiers doivent avoir un content-type présent '
                                . "dans la liste (sinon, le document n'est pas valide).\n"
                                . "La liste des content-type est maintenue par l'IANA : "
                                . 'https://www.iana.org/assignments/media-types/media-types.xhtml')
                        ->end()
                        ->scalarNode(FormulaireElement::MAX_FILE_SIZE->value)
                            ->info('Taille maximale du fichier en octet (optionnel,sans limite par défaut)')
                        ->end()
                        ->scalarNode(FormulaireElement::MAX_MULTIPLE_FILE_SIZE->value)
                          ->info('Taille maximale des fichiers du champ multiple '
                                  . '(optionnel,sans limite par défaut, cumulable avec max_file_size)')
                          ->end()
                        ->booleanNode(FormulaireElement::PROGRESS_BAR->value)
                            ->info('NE PLUS UTILISER - déprécié PA 3.0. Sur les champs de type fichier, permet '
                                . "d'ajouter une barre de progression (systématique a partir de la version 2.1.0)")
                        ->end()
                        ->arrayNode(FormulaireElement::SHOW_ROLE->value)
                            ->info("N'affiche cette information que pour certain role")
                        ->end()
                        ->append($this->addReadOnlyContentNode())
                        ->append($this->addValueNode())
                    ->end()
                ->end()
            ->end();
        return $treeBuilder->getRootNode();
    }

    private function addReadOnlyContentNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(FormulaireElement::READ_ONLY_CONTENT->value);
        $treeBuilder->getRootNode()
            ->info("(ne pas utiliser) permet de rendre un champs éditable n'importe quand "
                . "si une valeur est vérifié.\n"
                . "Exemple: \"has_reponse_lettre_courrier_simple: true\".\n"
                . 'Dans la plupart des cas, editable-content sur un état suffit.')
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->end();
        return $treeBuilder->getRootNode();
    }

    private function addValueNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(FormulaireElement::VALUE->value);
        $treeBuilder->getRootNode()
            ->info('uniquement pour le type select')
            ->normalizeKeys(false)
            ->scalarPrototype()
            ->end();
        return $treeBuilder->getRootNode();
    }

    private function addPageConditionNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(ModuleElement::PAGE_CONDITION->value);
        $treeBuilder->getRootNode()
            ->info('Détermine les règles permettant de savoir si une page doit être affiché ou non')
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->booleanPrototype()
                ->end()
                ->scalarPrototype()
                ->end()
            ->end();
        return $treeBuilder->getRootNode();
    }

    private function addActionNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(ModuleElement::ACTION->value);
        $treeBuilder->getRootNode()
            ->info("Définition de l'ensemble des actions qui peuvent être déclenché sur le dossier")
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->normalizeKeys(false)
            ->children()
                ->scalarNode(FormulaireElement::NAME->value)
                    ->info("Nom de l'action telle qu'elle apparait une fois réalisé (Envoyé)")
                ->end()
                ->scalarNode(ActionElement::NAME_ACTION->value)
                    ->info("Nom de l'action qui apparait sur les boutons de déclenchement des action (Envoyer)")
                ->end()
                ->scalarNode(ActionElement::ACTION_CLASS->value)->end()
                ->scalarNode('connecteur-type-data-seda-class-name')
                    ->info('Permet de spécifier le nom de la classe appelé pour la génération du bordereau SEDA')
                ->end()
                ->scalarNode(ActionElement::WARNING->value)
                    ->info('Si présent, une page intermédiaire avec confirmation du choix apparaît')
                ->end()
                ->scalarNode(ActionElement::ACTION_AUTOMATIQUE->value)->end()
                ->arrayNode(ActionElement::EDITABLE_CONTENT->value)
                    ->info('Identifiant des champs modifiables')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode(ActionElement::TYPE_ID_E->value)
                    ->scalarPrototype()->end()
                ->end()
                ->scalarNode(ActionElement::ACCUSE_DE_RECEPTION_ACTION->value)
                  ->info("l'action nécessite un accusé de réception avant d'être réalisé.")
                ->end()
                ->booleanNode(ActionElement::PAS_DANS_UN_LOT->value)
                  ->info('cette action ne peut pas être réalisée dans le cadre du traitement par lot')
                ->end()
                ->scalarNode(ActionElement::NUM_SAME_CONNECTEUR->value)
                    ->info('Si le flux utilise plusieurs connecteurs du même type, '
                            . "numéro d'ordre du connecteur à utiliser pour l'action considérée (débute à 0)")
                  ->defaultValue('0')
                ->end()
                ->scalarNode(ActionElement::ACTION_SELECTION->value)
                    ->info("l'action nécessite de choisir dans une liste d'entité spécifique. "
                            . "Ici, le type de l'entité spécifique")
                ->end()
                ->booleanNode(ActionElement::MODIFICATION_NO_CHANGE_ETAT->value)
                    ->info('Si true, alors après une modification du document, si celui-ci est dans cet état, '
                            . "alors il ne changera pas d'état")
                    ->defaultFalse()
                ->end()
                ->booleanNode(ActionElement::NO_WORKFLOW->value)->end()
                ->scalarNode(ActionElement::CONNECTEUR_TYPE->value)
                    ->info("Pour l'action standard indique dans quel type de connecteur l'action doit-être exécutée")
                ->end()
                ->scalarNode(ActionElement::CONNECTEUR_TYPE_ACTION->value)
                    ->info("Permet de spécifier le nom de la classe à exécuter dans le cadre de l'action standard")
                ->end()
                ->append($this->addRuleNode())
                ->append($this->addConnecteurTypeMappingNode())
                ->append($this->addTransformationsNode())
            ->end();
        return $treeBuilder->getRootNode();
    }

    private function addRuleNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(ActionElement::RULE->value);
        $treeBuilder->getRootNode()
            ->normalizeKeys(false)
                ->variablePrototype()->end()
            ->end();
        return $treeBuilder->getRootNode();
    }

    private function addConnecteurTypeMappingNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(ActionElement::CONNECTEUR_TYPE_MAPPING->value);
        $treeBuilder->getRootNode()
            ->info("Permet de spécifier le mapping entre les noms des éléments du document\n"
                    . "et les noms des éléments attendu par l'action du connecteur type\n"
                    . 'La valeur peut être un élément ou une liste d\'éléments')
            ->normalizeKeys(false)
            ->variablePrototype()
                ->validate()
                    ->ifTrue(static fn($value): bool => !self::isValidConnecteurTypeMappingValue($value))
                    ->thenInvalid('La valeur doit être un élément ou une liste non vide d\'éléments')
                ->end()
            ->end();
        return $treeBuilder->getRootNode();
    }

    private static function isValidConnecteurTypeMappingValue(mixed $value): bool
    {
        if ($value === null || \is_scalar($value)) {
            return true;
        }
        if (!\is_array($value) || $value === [] || !\array_is_list($value)) {
            return false;
        }
        foreach ($value as $element) {
            if (!\is_scalar($element)) {
                return false;
            }
        }
        return true;
    }

    private function addTransformationsNode(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(ActionElement::TRANSFORMATIONS->value);
        $treeBuilder->getRootNode()
            ->info("Permet de spécifier des transformations de champs du document\n"
                . 'par une expression twig')
            ->normalizeKeys(false)
            ->scalarPrototype()
            ->end();
        return $treeBuilder->getRootNode();
    }
}
