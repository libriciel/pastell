<?php

use Pastell\Step\AnnexeList;

class TdtAnnexeTypologieAnnexeChange extends ConnecteurTypeActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
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

        if (! $this->getDonneesFormulaire()->get($type_pj_element)) {
            return true;
        }

        $type_piece_fichier = $this->getDonneesFormulaire()->getFileContent($type_piece_fichier_element);
        if (! $type_piece_fichier) {
            return false;
        }

        foreach (json_decode($type_piece_fichier, true) as $file_info) {
            $type_fichier_array[$file_info['filename']][] = $file_info['typologie'];
        }

        $annexe_filename_list = AnnexeList::getFilenames(
            $this->getMappingValueList(AnnexeList::MAPPING_KEY),
            $this->getDonneesFormulaire()
        );

        $type_pj = [];
        foreach ($annexe_filename_list as $annexe_name) {
            if (empty($type_fichier_array[$annexe_name])) {
                $type_pj[] = "";
                continue;
            }
            $filename = array_shift($type_fichier_array[$annexe_name]);
            preg_match("#\((.{5})\)$#", $filename, $matches);
            $type_pj[] = $matches[1];
        }

        $this->getDonneesFormulaire()->removeFile($type_piece_fichier_element);
        $this->getDonneesFormulaire()->deleteField($type_piece_element);
        $this->getDonneesFormulaire()->setData($type_pj_element, json_encode($type_pj));

        $this->setLastMessage("Modification des fichiers ou de la nature : merci de revoir la typologie");
        return false;
    }

    public function updateJobQueueAfterExecution(): bool
    {
        return false;
    }
}
