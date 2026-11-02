<?php

declare(strict_types=1);

namespace Pastell\Service\Journal;

use Aws\S3\Exception\S3Exception;
use DocumentTypeFactory;
use JournalEntry;
use JournalEventType;
use JournalSQL;
use Pastell\Storage\StorageInterface;

final class JournalConsultationService
{
    public function __construct(
        private readonly JournalSQL $journalSQL,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly StorageInterface $storage,
        private readonly bool $use_external_storage_for_journal_proof,
    ) {
    }

    public function getList(
        int $id_e = 0,
        string $type = '',
        string $id_d = '',
        int $id_u = 0,
        int $offset = 0,
        int $limit = JournalSQL::DEFAULT_LIMIT,
        string $recherche = '',
        string $date_debut = '',
        string $date_fin = '',
        bool $tri_croissant = false,
        bool $with_preuve = true
    ): array {
        $result = $this->journalSQL->getAll($id_e, $type, $id_d, $id_u, $offset, $limit, $recherche, $date_debut, $date_fin, $tri_croissant);
        foreach ($result as $i => $line) {
            $documentType = $this->documentTypeFactory->getFluxDocumentType($line['document_type']);
            $result[$i]['document_type_libelle'] = $documentType->getName();
            $result[$i]['action_libelle'] = $documentType->getAction()->getActionName($line['action']);
            if (!$with_preuve) {
                unset($result[$i]['preuve']);
            } elseif ($result[$i]['preuve'] === '' && $this->use_external_storage_for_journal_proof) {
                $result[$i]['preuve'] = $this->getProof((int) $result[$i]['id_j']);
            }
        }
        return $result;
    }

    public function countAll(
        int $id_e,
        string $type,
        string $id_d,
        int $id_u,
        string $recherche,
        string $date_debut,
        string $date_fin
    ): int {
        return $this->journalSQL->countAll($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin);
    }

    public function getInfo(int $id_j): ?JournalEntry
    {
        $result = $this->journalSQL->getInfo($id_j);
        if ($result === null) {
            return null;
        }

        if ($result->preuve === '' && $this->use_external_storage_for_journal_proof) {
            $result->preuve = $this->getProof($result->id_j);
        }

        return $result;
    }

    public function getAllInfo(int $id_j): array|false
    {
        $result = $this->journalSQL->getAllInfo($id_j);

        if (!$result) {
            return $result;
        }

        $documentType = $this->documentTypeFactory->getFluxDocumentType($result['document_type']);
        $result['document_type_libelle'] = $documentType->getName();
        $result['action_libelle'] = $documentType->getAction()->getActionName($result['action']);

        if ($result['preuve'] === '' && $this->use_external_storage_for_journal_proof) {
            $result['preuve'] = $this->getProof((int) $id_j);
        }

        return $result;
    }

    public function getTypeAsString(int $type): string
    {
        return JournalEventType::tryFrom($type)?->label() ?? '';
    }

    /**
     * @throws S3Exception
     */
    private function getProof(int $id_j): string
    {
        try {
            return $this->storage->read($id_j . 'preuve.tsa');
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NoSuchKey') {
                return '';
            }
            throw $e;
        }
    }
}
