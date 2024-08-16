<?php

declare(strict_types=1);

use Pastell\Service\Document\DocumentTitre;
use Pastell\Service\Document\DocumentTransformService;

class TransformationFixeTransform extends ConnecteurTypeActionExecutor
{
    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws JsonException
     * @throws Exception
     */
    public function go(): bool
    {

        $transformationFixeFileElement = $this->getMappingValue('transformation_fixe_file');
        $hasTransformationFixeElement = $this->getMappingValue('has_transformation_fixe');
        $transformationFixeErrorState = $this->getMappingValue('transformation-fixe-error');

        $transformationData = $this->getTransformations();
        $donneesFormulaire = $this->getDonneesFormulaire();
        $documentTransformService = $this->objectInstancier->getInstance(DocumentTransformService::class);

        try {
            $modifiedFields = $documentTransformService->transform($donneesFormulaire, $transformationData);
            $this->addOnChange($modifiedFields);
        } catch (Exception $e) {
            $this->changeAction($transformationFixeErrorState, $e->getMessage());
            $this->notify(
                $transformationFixeErrorState,
                $this->type,
                'Erreur lors de la transformation: ' . $e->getMessage()
            );
            return false;
        }

        $documentTitre = $this->objectInstancier->getInstance(DocumentTitre::class);
        $documentTitre->update($this->id_d);

        if (!empty($modifiedFields)) {
            $donneesFormulaire->setData($hasTransformationFixeElement, true);
            $donneesFormulaire->addFileFromData(
                $transformationFixeFileElement,
                'transformation_fixe_file.json',
                json_encode($modifiedFields, JSON_THROW_ON_ERROR)
            );
        }

        $message = 'Transformation fixe terminée';
        $this->addActionOK($message);
        $this->notify($this->action, $this->type, $message);
        $this->setLastMessage($message);
        return true;
    }

    /**
     * @throws NotFoundException
     * @throws JsonException
     * @throws UnrecoverableException
     */
    private function addOnChange(array $modified_fields = []): void
    {
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($this->id_d);
        $actionExecutorFactory = $this->objectInstancier->getInstance(ActionExecutorFactory::class);

        foreach ($modified_fields as $id => $value) {
            $field = $donneesFormulaire->getFieldData($id)->getField();
            if ($field->getOnChange()) {
                $actionExecutorFactory->executeOnDocumentCritical(
                    $this->id_e,
                    $this->id_u,
                    $this->id_d,
                    $field->getOnChange(),
                    $this->id_destinataire,
                    $this->from_api,
                    $this->action_params,
                    $this->id_worker,
                    false
                );
            }
        }

        //FIXME: it's trash
        $actionExecutorFactory->setLastClassAction($this);
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($this->id_d);
        if (! $donneesFormulaire->isValidable()) {
            throw new UnrecoverableException(
                "[transformation fixe] Le dossier n'est pas valide : " . $donneesFormulaire->getLastError()
            );
        }
    }
}
