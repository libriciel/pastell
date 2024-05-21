<?php

class TypeDossierTransformationFixeEtape implements TypeDossierEtapeSetSpecificInformation
{
    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper
    ): array {

        $transformationFixeAction = $stringMapper->get('transformation-fixe');
        $transformation = $typeDossierEtape->specific_type_info['transformations'];

        if (isset($transformation)) {
            $explodedTransformation = explode(':', trim($transformation), 2);
            $result[DocumentType::ACTION][$transformationFixeAction]
            [Action::TRANSFORMATIONS][$explodedTransformation[0]] = $explodedTransformation[1] ?? null;
        }

        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][]
            = $stringMapper->get('transformation-fixe-error');
        return $result;
    }
}
