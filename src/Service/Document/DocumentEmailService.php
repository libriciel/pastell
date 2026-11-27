<?php

namespace Pastell\Service\Document;

use ActionChange;
use DocumentActionEntiteSQL;
use DocumentEmailReponseSQL;
use DocumentEmailSQL;
use DocumentEntiteSQL;
use DocumentSQL;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;
use UnrecoverableException;

class DocumentEmailService
{
    private const ACTION_ERROR = 'erreur';
    private const HIDDEN_KEY = 'XXXX-LA-CLE-NE-PEUT-ETRE-DIVULGUEE-ICI-XXXX';

    public function __construct(
        private readonly DocumentEmailSQL $documentEmailSQL,
        private readonly DocumentEmailReponseSQL $documentEmailReponseSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentEntiteSQL $documentEntiteSQL,
        private readonly DocumentActionEntiteSQL $documentActionEntiteSQL,
        private readonly ActionChange $actionChange,
        private readonly JournalEntryService $journalEntryService,
    ) {
    }

    public function getDocumentEmailFromIdReponse(string $documentId): array
    {
        $mailInfo = [];
        $reponse = $this->documentEmailReponseSQL->getInfoFromIdReponse($documentId);
        if (!empty($reponse)) {
            $mail = $this->documentEmailSQL->getById($reponse['id_de']);
        }
        if (!empty($mail)) {
            $mailInfo = $this->documentSQL->getInfo($mail['id_d']);
        }
        return $mailInfo;
    }

    public function addRecipient(string $id_d, string $email, DocumentEmailRecipientType $type): string
    {
        $key = $this->documentEmailSQL->getKey($id_d, $email);
        if ($key !== null) {
            return $key;
        }
        $key = md5($id_d . $email . mt_rand());
        $this->documentEmailSQL->insert($id_d, $email, $key, $type->value);
        return $key;
    }

    public function addError(int $id_de, string $body): void
    {
        $this->documentEmailSQL->setError($id_de, $body);
        $id_d = $this->documentEmailSQL->getById($id_de)['id_d'];
        if (!$this->documentEmailSQL->isAllInError($id_d)) {
            return;
        }
        $id_e = $this->documentEntiteSQL->getEntiteId($id_d);
        if ($this->documentActionEntiteSQL->getLastAction($id_e, $id_d) === self::ACTION_ERROR) {
            return;
        }
        $this->actionChange->addAction($id_d, $id_e, 0, self::ACTION_ERROR, 'Erreur : aucun email reçu');
    }

    public function getRecipients(string $id_d): array
    {
        $recipient_list = $this->documentEmailSQL->getAllByDocument($id_d);
        foreach ($recipient_list as $i => $recipient_info) {
            $documentEmailRecipientType = DocumentEmailRecipientType::tryFrom($recipient_info['type_destinataire']);
            $recipient_list[$i]['type_destinataire_libelle'] =
                $documentEmailRecipientType?->label() ?? $recipient_info['type_destinataire'];
            foreach ($this->decodeResponse($recipient_info) as $response_key => $response_value) {
                $recipient_list[$i][$response_key] = $response_value;
            }
        }
        return $recipient_list;
    }

    public function getRecipient(int $id_de): ?array
    {
        return $this->documentEmailSQL->getById($id_de);
    }

    public function getResponses(string $id_d): array
    {
        return $this->documentEmailReponseSQL->getAllReponse($id_d);
    }

    public function getResponseColumns(array $recipient_list): array
    {
        $column_list = [];
        foreach ($recipient_list as $recipient_info) {
            foreach (array_keys($this->decodeResponse($recipient_info)) as $response_key) {
                if (!\in_array($response_key, $column_list, true)) {
                    $column_list[] = $response_key;
                }
            }
        }
        return $column_list;
    }

    public function getLastErrorWithoutKey(array $document_email_info): string
    {
        return str_replace(
            $document_email_info['key'],
            self::HIDDEN_KEY,
            (string) $document_email_info['last_error']
        );
    }

    /**
     * @throws UnrecoverableException
     */
    public function readResponse(int $id_e, string $id_d, string $id_d_reponse, string $login): array
    {
        $response_info = $this->documentEmailReponseSQL->getInfoFromIdReponse($id_d_reponse);
        if (!$response_info) {
            throw new UnrecoverableException('Impossible de lire ce document');
        }
        $document_email_info = $this->documentEmailSQL->getById((int) $response_info['id_de']);
        if ($document_email_info === null || $document_email_info['id_d'] !== $id_d) {
            throw new UnrecoverableException('Impossible de lire ce document');
        }

        if (!$response_info['is_lu']) {
            $this->documentEmailReponseSQL->setLu($id_d_reponse);
            $this->journalEntryService->add(
                JournalEventType::SECURE_MAIL,
                $id_e,
                $id_d,
                "Lecture d'une réponse",
                \sprintf(
                    '%s a lu la réponse de %s (id_de=%s, id_d_reponse=%s)',
                    $login,
                    $document_email_info['email'],
                    $document_email_info['id_de'],
                    $response_info['id_d_reponse']
                )
            );
        }
        return $document_email_info;
    }

    private function decodeResponse(array $recipient_info): array
    {
        if (!$recipient_info['reponse']) {
            return [];
        }
        return (array) (json_decode($recipient_info['reponse']) ?? []);
    }
}
