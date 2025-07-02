<?php

namespace Pastell\Service;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DocumentSQL;
use DonneesFormulaireFactory;
use Exception;
use NotFoundException;
use UnrecoverableException;

class UpdateFieldService
{
    public const SCOPE_MODULE = 'module';
    public const SCOPE_CONNECTOR = 'connector';

    public function __construct(
        private DocumentSQL $documentSQL,
        private DonneesFormulaireFactory $donneesFormulaireFactory,
        private ConnecteurEntiteSQL $connecteurEntiteSql,
        private ConnecteurFactory $connecteurFactory,
        private SimpleTwigRenderer $simpleTwigRenderer,
    ) {
    }

    public function getAllDocuments(
        string $scope,
        string $type
    ): array {
        $documents = [];
        if ($scope === self::SCOPE_MODULE) {
            $documents = $this->documentSQL->getAllIdByType($type);
        } elseif ($scope === self::SCOPE_CONNECTOR) {
            $documents = $this->connecteurEntiteSql->getAllByConnecteurId($type);
        }
        return $documents;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    public function updateField(
        array $document,
        string $scope,
        string $field,
        string $twigExpression,
        ?string $dryRun = null
    ): string {
        if ($scope === self::SCOPE_MODULE) {
            $scopeId = 'id_d';
            $documentId = $document[$scopeId];
            $donneesFormulaire = $this->donneesFormulaireFactory->get($documentId);
        } else {
            $scopeId = 'id_ce';
            $documentId = $document[$scopeId];
            $donneesFormulaire = $this->connecteurFactory->getConnecteurConfig($documentId);
        }

        $old_value = $donneesFormulaire->get($field);
        $new_value = $this->simpleTwigRenderer->render(
            $twigExpression,
            $donneesFormulaire
        );
        if (!$dryRun) {
            $donneesFormulaire->setData($field, $new_value);
        }

        return sprintf(
            'Update %s=%s - id_e=%s. Replace field %s value `%s` by `%s`',
            $scopeId,
            $documentId,
            $document['id_e'],
            $field,
            $old_value,
            $new_value
        );
    }
}
