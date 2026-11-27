<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use DocumentSQL;
use DonneesFormulaireFactory;
use JournalEventType;
use NotFoundException;
use Pastell\Service\Journal\JournalEntryService;
use UnrecoverableException;
use UtilisateurSQL;

final class DocumentFileService
{
    private const MAX_FILE_NAME_LENGTH = 80;
    private const TRUNCATED_BASE_NAME_LENGTH = 76;

    public function __construct(
        private readonly DocumentSQL $documentSQL,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function getFileToDownload(int $id_e, int $id_u, string $id_d, string $field_name, int $num): DocumentFile
    {
        $document_info = $this->documentSQL->getInfo($id_d);
        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d, $document_info['type']);

        $file_path = $donneesFormulaire->getFilePath($field_name, $num);
        $file_name = $donneesFormulaire->get($field_name)[$num] ?? null;
        if ($file_name === null || !file_exists($file_path)) {
            throw new UnrecoverableException("Ce fichier n'existe pas");
        }

        $user_info = $this->utilisateurSQL->getInfo($id_u);
        $user_name = $user_info['prenom'] . ' ' . $user_info['nom'];
        $this->journalEntryService->add(
            JournalEventType::DOCUMENT_CONSULTATION,
            $id_e,
            $id_d,
            'Consulté',
            "$user_name a consulté le document $file_name"
        );

        return new DocumentFile($file_path, $this->truncateFileName($file_name));
    }

    private function truncateFileName(string $file_name): string
    {
        if (mb_strlen($file_name) <= self::MAX_FILE_NAME_LENGTH) {
            return $file_name;
        }
        $extension_position = mb_strrpos($file_name, '.');
        if ($extension_position === false) {
            return mb_substr($file_name, 0, self::MAX_FILE_NAME_LENGTH);
        }
        $base_name = mb_substr($file_name, 0, $extension_position);
        $extension = mb_substr($file_name, $extension_position + 1);
        return mb_substr($base_name, 0, self::TRUNCATED_BASE_NAME_LENGTH) . '.' . $extension;
    }
}
