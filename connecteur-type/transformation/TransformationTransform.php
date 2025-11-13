<?php

use Pastell\Service\Document\DocumentTitre;

class TransformationTransform extends ConnecteurTypeActionExecutor
{
    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws JsonException
     * @throws Exception
     */
    public function go(): bool
    {
        try {
            /** @var TransformationConnecteur $transformationConnecteur */
            $transformationConnecteur = $this->getConnecteur('transformation');
        } catch (Exception) {
            $message = "Il n'y a pas de connecteur de transformation associé. Poursuite du cheminement";
            $this->addActionOK($message);
            $this->notify($this->action, $this->type, $message);
            $this->setLastMessage($message);
            return true;
        }

        $transformationFileElement = $this->getMappingValue('transformation_file');
        $hasTransformationElement = $this->getMappingValue('has_transformation');
        $transformationErrorState = $this->getMappingValue('transformation-error');

        $donneesFormulaire = $this->getDonneesFormulaire();

        try {
            $modifiedFields = $transformationConnecteur->transform($donneesFormulaire);
            $this->addOnChange($modifiedFields);
        } catch (ValidationException) {
            $this->clearCache();
            $donneesFormulaire = $this->getDonneesFormulaire();
        } catch (Exception $e) {
            $this->changeAction($transformationErrorState, $e->getMessage());
            $this->notify(
                $transformationErrorState,
                $this->type,
                'Erreur lors de la transformation: ' . $e->getMessage()
            );
            return false;
        }

        $documentTitre = $this->objectInstancier->getInstance(DocumentTitre::class);
        $documentTitre->update($this->id_d);

        if (!empty($modifiedFields)) {
            $donneesFormulaire->setData($hasTransformationElement, true);
            $donneesFormulaire->addFileFromData(
                $transformationFileElement,
                'transformation_file.json',
                json_encode($modifiedFields, JSON_THROW_ON_ERROR)
            );
        }

        $message = 'Transformation terminée';
        $this->addActionOK($message);
        $this->notify($this->action, $this->type, $message);
        $this->setLastMessage($message);
        return true;
    }

    /**
     * @throws NotFoundException
     * @throws JsonException
     * @throws ValidationException
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
        if (!$donneesFormulaire->isValidable()) {
            throw new ValidationException(
                'Le document doit être modifié avant de pouvoir continuer le cheminement.'
            );
        }
    }
}
