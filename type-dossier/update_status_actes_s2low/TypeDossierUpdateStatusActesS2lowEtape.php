<?php

declare(strict_types=1);

final class TypeDossierUpdateStatusActesS2lowEtape implements TypeDossierEtapeSetSpecificInformation
{
    use TypeDossierRemoveFromEditableContent;

    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper,
    ): array {
        $changeStatusAction = $stringMapper->get('change-status-transaction');

        $result[DocumentType::ACTION][$changeStatusAction]
        [Action::CONNECTEUR_TYPE_MAPPING]['transaction'] = $typeDossierEtape->specific_type_info['transaction'];

        return $result;
    }
}
