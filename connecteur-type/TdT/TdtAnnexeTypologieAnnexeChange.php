<?php

class TdtAnnexeTypologieAnnexeChange extends ConnecteurTypeActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws JsonException
     * @throws Exception
     */
    public function go()
    {
        if (! empty($this->action_params['from_glaneur'])) {
            // Lors de la création par un glaneur, si on modifie la typologie et les annexes en même temps,
            // cette classe va systématiquement envoyer false et les onchange suivants ne sont pas executés
            // FIXME 3.0 mettre le from_glaneur à un niveau équivalent au from_api au niveau de ActionExecutor
            return true;
        }
        $type_pj_element = $this->getMappingValue('type_pj');
        $type_piece_fichier_element = $this->getMappingValue('type_piece_fichier');
        $type_piece_element = $this->getMappingValue('type_piece');
        $autre_document_attache = $this->getMappingValue('autre_document_attache');

        if (! $this->getDonneesFormulaire()->get($type_pj_element)) {
            return true;
        }

        $type_piece_fichier = $this->getDonneesFormulaire()->getFileContent($type_piece_fichier_element);
        if (! $type_piece_fichier) {
            return false;
        }

        try {
            $stored_pieces = json_decode($type_piece_fichier, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }
        $typologies = [];
        foreach ($stored_pieces as $stored_piece) {
            $typologies[$stored_piece['filename']][] = $stored_piece['typologie'];
        }

        $valid_codes = $this->getValidCodesForCurrentNature();
        $arrete = $this->getDonneesFormulaire()->get($this->getMappingValue('arrete')) ?: [];
        $annexes = $this->getDonneesFormulaire()->get($autre_document_attache) ?: [];

        $pieces = [];
        $type_pj = [];
        foreach (array_merge($arrete, $annexes) as $index => $filename) {
            $typologie = empty($typologies[$filename]) ? '' : array_shift($typologies[$filename]);
            $code = $this->extractCodeFromTypologie($typologie);
            if ($valid_codes !== null && $code !== '' && ! array_key_exists($code, $valid_codes)) {
                $typologie = '';
                $code = '';
            }
            $pieces[] = ['filename' => $filename, 'typologie' => $typologie];
            if ($index >= count($arrete)) {
                $type_pj[] = $code;
            }
        }

        $this->getDonneesFormulaire()->setData(
            $type_pj_element,
            json_encode($type_pj, JSON_THROW_ON_ERROR)
        );

        if (in_array('', array_column($pieces, 'typologie'), true)) {
            $this->getDonneesFormulaire()->deleteField($type_piece_element);
            $this->setLastMessage('Modification des fichiers ou de la nature : merci de revoir la typologie');
        } else {
            $this->getDonneesFormulaire()->setData($type_piece_element, count($pieces) . ' fichier(s) typé(s)');
        }

        $this->getDonneesFormulaire()->addFileFromData(
            $type_piece_fichier_element,
            $this->getDonneesFormulaire()->getFileName($type_piece_fichier_element) ?: 'type_piece.json',
            json_encode($pieces, JSON_THROW_ON_ERROR)
        );
        return true;
    }

    private function extractCodeFromTypologie(string $typologie): string
    {
        preg_match('#\((.{5})\)$#', $typologie, $matches);
        return $matches[1] ?? '';
    }

    /**
     * @return array<string, string>|null
     */
    private function getValidCodesForCurrentNature(): ?array
    {
        try {
            $config = $this->getConnecteurConfigByType(TdtConnecteur::FAMILLE_CONNECTEUR);
            return $this->objectInstancier->getInstance(ActesTypePJ::class)->getTypePJListeForClassification(
                $config->getFilePath($this->getMappingValue('classification_file')),
                $this->getDonneesFormulaire()->get($this->getMappingValue('acte_nature'))
            );
        } catch (Exception $e) {
            $this->getLogger()->warning(
                'Impossible de valider les typologies via la classification TdT : ' . $e->getMessage()
            );
            return null;
        }
    }

    public function updateJobQueueAfterExecution(): bool
    {
        return false;
    }
}
