<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentIndexSQL;
use DocumentSQL;
use DocumentTypeFactory;
use DonneesFormulaireFactory;
use NotFoundException;
use UnrecoverableException;

final class DocumentReindexService
{
    public function __construct(
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentIndexSQL $documentIndexSQL,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
    ) {
    }

    /**
     * @throws UnrecoverableException
     */
    public function getDocumentsToReindex(string $type, array $field_name_list, int $offset = 0, int $limit = -1): array
    {
        if (!$this->documentTypeFactory->isTypePresent($type)) {
            throw new UnrecoverableException("Le type de dossier $type n'existe pas sur cette plateforme.");
        }
        $formulaire = $this->documentTypeFactory->getFluxDocumentType($type)->getFormulaire();
        foreach ($field_name_list as $field_name) {
            $field = $formulaire->getField($field_name);
            if (!$field) {
                throw new UnrecoverableException(
                    "Le champs $field_name n'existe pas pour le type de dossier $type"
                );
            }
            if (!$field->isIndexed()) {
                throw new UnrecoverableException("Le champs $type:$field_name n'est pas indexé");
            }
        }

        $document_list = $this->documentSQL->getAllByType($type);
        if ($limit > 0) {
            $document_list = \array_slice($document_list, $offset, $limit);
        }
        return $document_list;
    }

    /**
     * @throws NotFoundException
     */
    public function reindexDocument(string $id_d, array $field_name_list): void
    {
        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);
        foreach ($field_name_list as $field_name) {
            $field_value = $donneesFormulaire->getFieldData($field_name)->getValueForIndex();
            $this->documentIndexSQL->index($id_d, $field_name, $field_value);
        }
        $this->donneesFormulaireFactory->clearCache();
    }
}
