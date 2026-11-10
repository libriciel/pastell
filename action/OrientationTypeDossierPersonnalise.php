<?php

use Pastell\Service\TypeDossier\TypeDossierImportService;
use Pastell\Service\TypeDossier\TypeDossierManager;

class OrientationTypeDossierPersonnalise extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function go()
    {
        $module_id = $this->getDocumentType()->getModuleId();

        $id_t = $this->objectInstancier->getInstance(TypeDossierSQL::class)->getByIdTypeDossier($module_id);
        if ($id_t) {
            $typeDossier = $this->objectInstancier
                ->getInstance(TypeDossierManager::class)
                ->getTypeDossierProperties($id_t);
        } else {
            $studio_def = $this->getDocumentType()->getStudioDefinition();
            $info = $this->objectInstancier
                ->getInstance(TypeDossierImportService::class)
                ->getInfoFromFileContent($studio_def);
            if (!$info) {
                throw new UnrecoverableException(
                    "Impossible de trouver le type de dossier studio pour choisir la prochaine action"
                );
            }
            $typeDossier = $info[2];
        }

        $typeDossierDefinition = $this->objectInstancier->getInstance(TypeDossierService::class);

        $last_action = $this->getDocumentActionEntiteSQL()->getLastAction($this->id_e, $this->id_d) ?? '';

        $cheminement_list = [];

        $onglet_num = $this->getDonneesFormulaire()->getFormulaire()->getTabNumber("Cheminement");
        if ($onglet_num) {
            $cheminement_fieldData_list = $this->getDonneesFormulaire()
                ->getFieldDataList('editeur', $onglet_num);
            /** @var FieldData $field */
            foreach ($cheminement_fieldData_list as $field) {
                $cheminement_list[] = boolval($field->getValueForIndex() == 'OUI');
            }
        }

        try {
            $next_action = $typeDossierDefinition->getNextActionFromTypeDossier(
                $typeDossier,
                $last_action,
                $cheminement_list
            );
        } catch (TypeDossierException $exception) {
            $message = "Impossible de sélectionner l'action suivante de $last_action : " . $exception->getMessage();
            $this->notify('fatal-error', $this->type, $message);
            $this->changeAction('fatal-error', $message);
            return false;
        }

        $message = "sélection automatique de l'action suivante";
        $this->notify($next_action, $this->type, $message);
        $this->changeAction($next_action, $message);
        return true;
    }
}
