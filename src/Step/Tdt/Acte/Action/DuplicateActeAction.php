<?php

declare(strict_types=1);

namespace Pastell\Step\Tdt\Acte\Action;

final class DuplicateActeAction extends \ConnecteurTypeActionExecutor
{
    /**
     * @throws \UnrecoverableException
     * @throws \ForbiddenException
     * @throws \NotFoundException
     */
    public function go(): bool
    {
        $currentDocument = $this->getDocument()->getInfo($this->id_d);

        $newDocumentId = $this->objectInstancier->getInstance(\DocumentCreationService::class)->createDocument(
            (int)$this->id_e,
            $this->id_u,
            $this->type
        );

        foreach (
            [
                $this->getMappingValue('objet'),
                $this->getMappingValue('acte_nature'),
                $this->getMappingValue('date_de_lacte'),
                $this->getMappingValue('document_papier'),
                $this->getMappingValue('classification'),
            ] as $field
        ) {
            $data[$field] = $this->getDonneesFormulaire()->get($field);
        }

        $cheminementTabNumber = $this->getDonneesFormulaire()->getFormulaire()->getTabNumber('Cheminement');
        if ($cheminementTabNumber !== false) {
            $cheminementFieldDataList = $this->getDonneesFormulaire()
                ->getFieldDataList('editeur', $cheminementTabNumber);
            /** @var \FieldData $field */
            foreach ($cheminementFieldDataList as $field) {
                $data[$field->getField()->getName()] = $field->getValueForIndex() === 'OUI';
            }
        }

        $this->objectInstancier->getInstance(\DocumentModificationService::class)
            ->modifyDocument(
                $this->id_e,
                $this->id_u,
                $newDocumentId,
                new \Recuperateur($data),
                new \FileUploader(),
                true
            );

        $this->setLastMessage(\sprintf('Le dossier %s a été dupliqué', $currentDocument['titre']));
        return true;
    }
}
