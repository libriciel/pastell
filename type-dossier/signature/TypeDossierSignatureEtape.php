<?php

class TypeDossierSignatureEtape implements TypeDossierEtapeSetSpecificInformation
{
    use TypeDossierRemoveFromEditableContent;

    public const HAS_DATA_LIMITE = 'has_date_limite';
    public const HAS_METADATA_IN_JSON = 'has_metadata_in_json';
    public const CONTINUE_AFTER_REFUSAL = 'continue_after_refusal';

    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper
    ): array {
        $ongletName = $stringMapper->get('iparapheur');
        $sendIparapheurAction = $stringMapper->get('send-iparapheur');
        $sendSignatureErrorAction = $stringMapper->get('send-signature-error');
        $verifIparapheurAction = $stringMapper->get('verif-iparapheur');
        $checkSignatureErrorState = $stringMapper->get('erreur-verif-iparapheur');
        $rejetIparapheurAction = $stringMapper->get('rejet-iparapheur');
        $hasDateLimiteElement = $stringMapper->get('has_date_limite');
        $dateLimiteElement = $stringMapper->get('date_limite');
        $jsonMetadataElement = $stringMapper->get('json_metadata');
        $continue_after_refusal = $stringMapper->get('continue_after_refusal');
        $primo_signature_element = $stringMapper->get('primo_signature_detachee');

        if (empty($result[DocumentType::ACTION][$sendIparapheurAction][Action::CONNECTEUR_TYPE_MAPPING])) {
            $result[DocumentType::ACTION][$sendIparapheurAction][Action::CONNECTEUR_TYPE_MAPPING] = [];
        }

        if (empty($result[DocumentType::ACTION][$verifIparapheurAction][Action::CONNECTEUR_TYPE_MAPPING])) {
            $result[DocumentType::ACTION][$verifIparapheurAction][Action::CONNECTEUR_TYPE_MAPPING] = [];
        }

        if (empty($typeDossierEtape->specific_type_info[self::HAS_DATA_LIMITE])) {
            unset(
                $result[DocumentType::FORMULAIRE][$ongletName][$hasDateLimiteElement],
                $result[DocumentType::FORMULAIRE][$ongletName][$dateLimiteElement]
            );
            $this->removeFromEditableContent(
                [$hasDateLimiteElement,$dateLimiteElement],
                $result
            );
        } else {
            $result[DocumentType::ACTION][$sendIparapheurAction]
            [Action::CONNECTEUR_TYPE_MAPPING]['iparapheur_has_date_limite'] = $hasDateLimiteElement;
            $result[DocumentType::ACTION][$sendIparapheurAction]
            [Action::CONNECTEUR_TYPE_MAPPING]['iparapheur_date_limite'] = $dateLimiteElement;
            $result[DocumentType::ACTION][$verifIparapheurAction]
            [Action::CONNECTEUR_TYPE_MAPPING]['iparapheur_has_date_limite'] = $hasDateLimiteElement;
            $result[DocumentType::ACTION][$verifIparapheurAction]
            [Action::CONNECTEUR_TYPE_MAPPING]['iparapheur_date_limite'] = $dateLimiteElement;
        }

        if (empty($typeDossierEtape->specific_type_info[self::HAS_METADATA_IN_JSON])) {
            unset($result[DocumentType::FORMULAIRE][$ongletName][$jsonMetadataElement]);
            unset($result[DocumentType::ACTION][$sendIparapheurAction]
                [Action::CONNECTEUR_TYPE_MAPPING]['json_metadata']);
            $this->removeFromEditableContent(
                [$jsonMetadataElement],
                $result
            );
        }
        if (empty($typeDossierEtape->specific_type_info['has_primo_signature'])) {
            unset(
                $result[DocumentType::FORMULAIRE][$ongletName][$primo_signature_element],
                $result[DocumentType::ACTION][$sendIparapheurAction][Action::CONNECTEUR_TYPE_MAPPING]['primo_signature_detachee'],
            );

            $this->removeFromEditableContent(
                [$primo_signature_element],
                $result
            );
        }

        if (!empty($typeDossierEtape->specific_type_info[self::CONTINUE_AFTER_REFUSAL])) {
            $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION]
            [Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][] = $rejetIparapheurAction;
            if ($typeDossierEtape->automatique) {
                $result[DocumentType::ACTION][$rejetIparapheurAction]
                [Action::ACTION_AUTOMATIQUE] = TypeDossierTranslator::ORIENTATION;
            }
        }

        foreach (
            [
                'objet' => 'libelle_parapheur',
                'document' => 'document_a_signer',
                'autre_document_a_signer' => 'autre_document_a_signer',
                'autre_document_attache' => 'annexe'
            ] as $mapping_key => $specific_key
        ) {
            if (!empty($typeDossierEtape->specific_type_info[$specific_key])) {
                $result[DocumentType::ACTION][$sendIparapheurAction]
                [Action::CONNECTEUR_TYPE_MAPPING][$mapping_key] = $typeDossierEtape->specific_type_info[$specific_key];
                $result[DocumentType::ACTION][$verifIparapheurAction]
                [Action::CONNECTEUR_TYPE_MAPPING][$mapping_key] = $typeDossierEtape->specific_type_info[$specific_key];
            }
        }

        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE]
                [Action::ACTION_RULE_LAST_ACTION][] = $rejetIparapheurAction;
        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE]
                [Action::ACTION_RULE_LAST_ACTION][] = $sendSignatureErrorAction;
        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE]
                [Action::ACTION_RULE_LAST_ACTION][] = $checkSignatureErrorState;
        $result[DocumentType::ACTION][Action::MODIFICATION][Action::ACTION_RULE]
                [Action::ACTION_RULE_LAST_ACTION][] = $sendSignatureErrorAction;

        return $result;
    }
}
