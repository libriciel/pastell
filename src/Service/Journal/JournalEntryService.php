<?php

declare(strict_types=1);

namespace Pastell\Service\Journal;

use Date;
use DocumentSQL;
use Exception;
use Horodateur;
use JournalEventType;
use JournalSQL;
use Monolog\Logger;
use Pastell\Storage\StorageInterface;
use UtilisateurSQL;

final class JournalEntryService
{
    public const NO_ID_D = '';

    private $id_u = 0;
    private ?Horodateur $horodateur = null;

    public function __construct(
        private readonly JournalSQL $journalSQL,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly Logger $logger,
        private readonly bool $disable_journal_horodatage,
        private readonly bool $use_external_storage_for_journal_proof,
        private StorageInterface $storage,
    ) {
    }

    public function setInterfaceStorage(StorageInterface $storageInterface): void
    {
        $this->storage = $storageInterface;
    }

    public function setHorodateur(Horodateur $horodateur): void
    {
        $this->horodateur = $horodateur;
    }

    public function setId($id_u): void
    {
        $this->id_u = $id_u;
    }

    public function addConsultation($id_e, $id_d, $id_u): int|false
    {
        if ($this->journalSQL->countConsultation($id_u, $id_d)) {
            return false;
        }
        $infoUtilisateur = $this->utilisateurSQL->getInfo($id_u);
        $nom = $infoUtilisateur['prenom'] . ' ' . $infoUtilisateur['nom'];
        return $this->add(JournalEventType::DOCUMENT_CONSULTATION, $id_e, $id_d, 'Consulté', "$nom a consulté le dossier");
    }

    public function add(JournalEventType $type_journal, $id_e, $id_d, $action, $message): int
    {
        return $this->addSQL($type_journal, $id_e, $this->id_u, $id_d, $action, $message);
    }

    public function addActionAutomatique(JournalEventType $type, $id_e, $id_d, $action, $message): int
    {
        return $this->addSQL($type, $id_e, 0, $id_d, $action, $message);
    }

    public function addSQL(JournalEventType $type, $id_e, $id_u, $id_d, $action, $message): int
    {
        if ($id_d) {
            $document_info = $this->documentSQL->getInfo($id_d);
            $document_type = $document_info['type'] ?? '';
        } else {
            $document_type = '';
            $id_d = 0;
        }
        if (!$id_e) {
            $id_e = '0';
        }
        if (! $action) {
            $action = '';
        }

        $now = date(Date::DATE_ISO);
        $type = $type->value;
        $message_horodate = "$type - $id_e - $id_u - $id_d - $action - $message - $now - $document_type";

        $preuve = '';
        $date_horodatage = '';

        if ($this->horodateur !== null && ! $this->disable_journal_horodatage) {
            $preuve = $this->horodateur->getTimestampReply($message_horodate);
        }
        if ($preuve) {
            $date_horodatage = $this->horodateur->getTimeStamp($preuve);
            if (! $date_horodatage) {
                $preuve = '';
                $date_horodatage = '';
            }
        }

        if (!$this->use_external_storage_for_journal_proof) {
            $id_j = $this->journalSQL->insert($type, $id_e, $id_u, $id_d, $action, $message, $now, $message_horodate, $date_horodatage, $document_type, $preuve);
        } else {
            $id_j = $this->journalSQL->insert($type, $id_e, $id_u, $id_d, $action, $message, $now, $message_horodate, $date_horodatage, $document_type, null);
            $this->saveProof($id_j, $preuve);
        }

        if (! $preuve && ! $this->disable_journal_horodatage) {
            $this->journalSQL->insertAttentePreuve($id_j);
        }

        $this->logger->info("Ajout au journal (id_j=$id_j): " . $message_horodate);

        return $id_j;
    }

    public function saveProof(int $id_j, string $preuve): void
    {
        $this->storage->write($id_j . 'preuve.tsa', $preuve);
    }

    public function horodateAll(): void
    {
        if ($this->horodateur === null) {
            throw new Exception("Aucun horodateur configuré\n");
        }

        foreach ($this->journalSQL->getAttentePreuveIdList() as $id_j) {
            $info = $this->journalSQL->getInfo($id_j);
            $preuve = $this->horodateur->getTimestampReply($info->message_horodate);
            $date_horodatage = $this->horodateur->getTimeStamp($preuve);
            if ($this->use_external_storage_for_journal_proof) {
                $this->saveProof((int) $id_j, $preuve);
                $this->journalSQL->updateHorodatage($info->id_j, $date_horodatage);
            } else {
                $this->journalSQL->updateProof($info->id_j, $date_horodatage, $preuve);
            }
            echo "$info->id_j horodaté : $date_horodatage\n";
            $this->journalSQL->deleteAttentePreuve((int) $id_j);
        }
    }
}
