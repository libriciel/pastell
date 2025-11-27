<?php

declare(strict_types=1);

class TypeDossierTransformationEtape implements TypeDossierEtapeSetSpecificInformation
{
    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper
    ): array {

        $transformation_error = $stringMapper->get('transformation-error');
        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE]
        [Action::ACTION_RULE_LAST_ACTION][] = $transformation_error;

        /*
         * Voir "Il est possible de modifier un dossier en état "Erreur lors de la transformation du dossier" #2332"
         * Dans type-dossier-etape.yml on renseigne `action-automatique: orientation` pour que l'action
         * soit construite (TypeDossierTranslator) avec les editable-content (entre autre)
         * Au final, ici, on unset `action-automatique: orientation` pour ne pas lancer l'action automatique
         */
        unset($result[DocumentType::ACTION][$transformation_error][Action::ACTION_AUTOMATIQUE]);

        return $result;
    }
}
