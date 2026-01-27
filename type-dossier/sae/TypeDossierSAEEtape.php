<?php

class TypeDossierSAEEtape implements TypeDossierEtapeSetSpecificInformation
{
    use TypeDossierRemoveFromEditableContent;

    public const CONTINUE_AFTER_REFUSAL = 'continue_after_refusal';
    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper
    ): array {
        $config_sae = $stringMapper->get('Configuration SAE');
        $rejet_sae_action = $stringMapper->get('rejet-sae');
        $erreurEnvoieSaeAction = $stringMapper->get('erreur-envoie-sae');
        $generateSipAction = $stringMapper->get('generate-sip');
        $saeConfig = $stringMapper->get('sae_config');

        if (empty($typeDossierEtape->specific_type_info['sae_has_metadata_in_json'])) {
            unset(
                $result[DocumentType::FORMULAIRE][$config_sae],
                $result[DocumentType::PAGE_CONDITION][$config_sae],
                $result[DocumentType::ACTION][$generateSipAction][Action::CONNECTEUR_TYPE_MAPPING]['sae_config'],
            );
            $this->removeFromEditableContent([$saeConfig], $result);
        }

        if (!empty($typeDossierEtape->specific_type_info[self::CONTINUE_AFTER_REFUSAL])) {
            $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION]
            [Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][] = $rejet_sae_action;
            if ($typeDossierEtape->automatique) {
                $result[DocumentType::ACTION][$rejet_sae_action]
                [Action::ACTION_AUTOMATIQUE] = TypeDossierTranslator::ORIENTATION;
            }
        }

        foreach ([$rejet_sae_action, $erreurEnvoieSaeAction] as $action) {
            $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][] = $action;
        }
        return $result;
    }
}
