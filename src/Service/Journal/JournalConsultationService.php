<?php

declare(strict_types=1);

namespace Pastell\Service\Journal;

use Aws\S3\Exception\S3Exception;
use DocumentTypeFactory;
use JournalEntry;
use JournalSQL;
use Pastell\Storage\StorageInterface;

final class JournalConsultationService
{
    private const TYPE_STRING = [
        1 => 'Action sur un dossier',
        'Notification',
        'Gestion des entités',
        'Gestion des utilisateurs',
        'Mail sécurisé',
        'Connexion',
        'Consultation de dossier ou de document',
        'Envoi de mail',
        'Erreur lors de la tentative d\'une action',
        'Programmation d\'un traitement par lot',
        'Test',
        'Action sur un type de dossier personnalisé',
        'Action sur le journal',
        'Action par commande',
    ];

    public function __construct(
        private readonly JournalSQL $journalSQL,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly StorageInterface $storage,
        private readonly bool $use_external_storage_for_journal_proof,
    ) {
    }

    public function getList(
        $id_e = false,
        $type = false,
        $id_d = false,
        $id_u = false,
        $offset = 0,
        $limit = JournalSQL::DEFAULT_LIMIT,
        $recherche = '',
        $date_debut = false,
        $date_fin = false,
        $tri_croissant = false,
        $with_preuve = true
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

    public function countAll($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin): int
    {
        return $this->journalSQL->countAll($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin);
    }

    public function getInfo($id_j): ?JournalEntry
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

    public function getAllInfo($id_j): array|false
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

    public function getTypeAsString($type): string
    {
        return self::TYPE_STRING[$type];
    }

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
