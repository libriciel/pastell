<?php

class TypeDossierActesEtape implements TypeDossierEtapeSetSpecificInformation
{
    public const FICHIER_ACTE = 'fichier_acte';
    public const ARRETE = 'arrete';
    public const FICHIER_ANNEXE = 'fichier_annexe';
    public const AUTRE_DOCUMENT_ATTACHE = 'autre_document_attache';
    public const OBJET_ACTE = 'objet_acte';
    public const DROIT_SPECIFIQUE = "droit_specifique";
    public const DROIT_SPECIFIQUE_TELETRANSMETTRE = 'teletransmettre';
    public const THRESHOLD_SIZE = '157286400';

    public function setSpecificInformation(
        TypeDossierEtapeProperties $typeDossierEtape,
        array $result,
        StringMapper $stringMapper
    ): array {
        $typePieceAction = $stringMapper->get('type-piece');
        $sendTdtAction = $stringMapper->get('send-tdt');
        $sendTdtErrorAction = $stringMapper->get('send-tdt-erreur');
        $verifTdtAction = $stringMapper->get('verif-tdt');
        $annulerTdtAction = $stringMapper->get('annuler-tdt');
        $tamponnerTdtAction = $stringMapper->get('tamponner-tdt');
        $teletransmissionTdtAction = $stringMapper->get('teletransmission-tdt');
        $typologieChange = $stringMapper->get('typologie-change');
        $autreDocumentAttacheChange = $stringMapper->get('autre_document_attache-change');
        $acquiterTdtAction = $stringMapper->get('acquiter-tdt');

        if (!empty($typeDossierEtape->specific_type_info[self::FICHIER_ACTE])) {
            $result[DocumentType::ACTION][$typePieceAction][Action::CONNECTEUR_TYPE_MAPPING][self::ARRETE]
                = $typeDossierEtape->specific_type_info[self::FICHIER_ACTE];
            $result[DocumentType::ACTION][$sendTdtAction][Action::CONNECTEUR_TYPE_MAPPING][self::ARRETE]
                = $typeDossierEtape->specific_type_info[self::FICHIER_ACTE];
            $result[DocumentType::ACTION][$verifTdtAction][Action::CONNECTEUR_TYPE_MAPPING][self::ARRETE]
                = $typeDossierEtape->specific_type_info[self::FICHIER_ACTE];
            $result[DocumentType::ACTION][$tamponnerTdtAction][Action::CONNECTEUR_TYPE_MAPPING][self::ARRETE]
                = $typeDossierEtape->specific_type_info[self::FICHIER_ACTE];
            $result[DocumentType::ACTION][$typologieChange][Action::CONNECTEUR_TYPE_MAPPING][self::ARRETE]
                = $typeDossierEtape->specific_type_info[self::FICHIER_ACTE];
            $result[DocumentType::THRESHOLD_SIZE] = self::THRESHOLD_SIZE;
            $result[DocumentType::THRESHOLD_FIELDS][] = $typeDossierEtape->specific_type_info[self::FICHIER_ACTE];
        }
        $fichierAnnexeElements = $this->getFichierAnnexeElements($typeDossierEtape);
        if ($fichierAnnexeElements) {
            $mappingValue = count($fichierAnnexeElements) === 1 ? $fichierAnnexeElements[0] : $fichierAnnexeElements;
            $result[DocumentType::ACTION][$typePieceAction][Action::CONNECTEUR_TYPE_MAPPING]
                [self::AUTRE_DOCUMENT_ATTACHE] = $mappingValue;
            $result[DocumentType::ACTION][$sendTdtAction][Action::CONNECTEUR_TYPE_MAPPING]
                [self::AUTRE_DOCUMENT_ATTACHE] = $mappingValue;
            $result[DocumentType::ACTION][$verifTdtAction][Action::CONNECTEUR_TYPE_MAPPING]
                [self::AUTRE_DOCUMENT_ATTACHE] = $mappingValue;
            $result[DocumentType::ACTION][$tamponnerTdtAction][Action::CONNECTEUR_TYPE_MAPPING]
                [self::AUTRE_DOCUMENT_ATTACHE] = $mappingValue;
            $result[DocumentType::ACTION][$typologieChange][Action::CONNECTEUR_TYPE_MAPPING]
                [self::AUTRE_DOCUMENT_ATTACHE] = $mappingValue;
            $result[DocumentType::ACTION][$autreDocumentAttacheChange][Action::CONNECTEUR_TYPE_MAPPING]
                [self::AUTRE_DOCUMENT_ATTACHE] = $mappingValue;
            foreach ($fichierAnnexeElements as $fichierAnnexeElement) {
                $result[DocumentType::THRESHOLD_FIELDS][] = $fichierAnnexeElement;
            }
        }
        if (!empty($typeDossierEtape->specific_type_info[self::OBJET_ACTE])) {
            $result[DocumentType::ACTION][$sendTdtAction][Action::CONNECTEUR_TYPE_MAPPING]['objet']
                = $typeDossierEtape->specific_type_info[self::OBJET_ACTE];
            $result[DocumentType::ACTION][$verifTdtAction][Action::CONNECTEUR_TYPE_MAPPING]['objet']
                = $typeDossierEtape->specific_type_info[self::OBJET_ACTE];
            $result[DocumentType::ACTION][$tamponnerTdtAction][Action::CONNECTEUR_TYPE_MAPPING]['objet']
                = $typeDossierEtape->specific_type_info[self::OBJET_ACTE];
            $result[DocumentType::ACTION]['duplicate'][Action::CONNECTEUR_TYPE_MAPPING]['objet']
                = $typeDossierEtape->specific_type_info[self::OBJET_ACTE];
        }
        if (!empty($typeDossierEtape->specific_type_info[self::DROIT_SPECIFIQUE])) {
            $result[DocumentType::ACTION][$teletransmissionTdtAction]
                [Action::ACTION_RULE][Action::ACTION_RULE_DROIT_ID_U]
                = sprintf('%s:%s', $result['__temporary_id'], self::DROIT_SPECIFIQUE_TELETRANSMETTRE);
        }

        reset($result[DocumentType::FORMULAIRE]);
        $onglet1 = key($result[DocumentType::FORMULAIRE]);

        foreach ($fichierAnnexeElements as $fichierAnnexeElement) {
            if (!empty($result[DocumentType::FORMULAIRE][$onglet1][$fichierAnnexeElement])) {
                $result[DocumentType::FORMULAIRE][$onglet1][$fichierAnnexeElement]
                    ['onchange'] = 'autre_document_attache-change';
            }
        }

        $result[DocumentType::ACTION][Action::MODIFICATION][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][]
            = $sendTdtErrorAction;
        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][]
            = $sendTdtErrorAction;
        $result[DocumentType::ACTION]['supression'][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][]
            = $annulerTdtAction;

        $go = false;
        foreach ($result[DocumentType::ACTION] as $action_id => $action_info) {
            if (! $go && $action_id !== $acquiterTdtAction) {
                continue;
            }
            $go = true;

            if (
                in_array($action_id, $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION][Action::ACTION_RULE]
                [Action::ACTION_RULE_LAST_ACTION], true)
            ) {
                $this->makeEditable($action_id, $result, $stringMapper);
            }
        }
        $this->makeEditable('termine', $result, $stringMapper);

        $result['champs-affiches'][] = $stringMapper->get('numero_de_lacte');
        $result['champs-recherche-avancee'][] = $stringMapper->get('numero_de_lacte');
        $result['champs-recherche-avancee'][] = $stringMapper->get('acte_unique_id');

        return $result;
    }

    private function getFichierAnnexeElements(TypeDossierEtapeProperties $typeDossierEtape): array
    {
        return array_values(
            array_filter((array)($typeDossierEtape->specific_type_info[self::FICHIER_ANNEXE] ?? []))
        );
    }

    private function makeEditable(string $actionId, array &$result, StringMapper $stringMapper): void
    {
        $actePublicationDate = $stringMapper->get('acte_publication_date');
        if (empty($result[DocumentType::ACTION][$actionId][Action::EDITABLE_CONTENT])) {
            $result[DocumentType::ACTION][$actionId][Action::EDITABLE_CONTENT] = [];
        }
        $result[DocumentType::ACTION][$actionId][Action::EDITABLE_CONTENT][] = $actePublicationDate;
        $result[DocumentType::ACTION][$actionId][Action::MODIFICATION_NO_CHANGE_ETAT] = true;
        $result[DocumentType::ACTION][Action::MODIFICATION][Action::ACTION_RULE][Action::ACTION_RULE_LAST_ACTION][]
            = $actionId;
    }
}
