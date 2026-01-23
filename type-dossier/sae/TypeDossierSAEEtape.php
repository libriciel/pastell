<?php

class TypeDossierSAEEtape implements TypeDossierEtapeSetSpecificInformation
{
    use TypeDossierRemoveFromEditableContent;

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

        foreach ([$rejet_sae_action, $erreurEnvoieSaeAction] as $action) {
            $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][] = $action;
        }
        return $result;
    }
}
