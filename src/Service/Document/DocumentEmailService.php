<?php

namespace Pastell\Service\Document;

use ActionCreatorSQL;
use DocumentActionEntite;
use DocumentEmailReponseSQL;
use DocumentEmailSQL;
use DocumentEntiteSQL;
use DocumentSQL;

class DocumentEmailService
{
    private const ACTION_ERROR = 'erreur';

    public function __construct(
        private readonly DocumentEmailSQL $documentEmailSQL,
        private readonly DocumentEmailReponseSQL $documentEmailReponseSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentEntiteSQL $documentEntiteSQL,
        private readonly DocumentActionEntite $documentActionEntite,
        private readonly ActionCreatorSQL $actionCreatorSQL,
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
        if ($this->documentActionEntite->getLastAction($id_e, $id_d) === self::ACTION_ERROR) {
            return;
        }
        $this->actionCreatorSQL->addAction($id_e, 0, self::ACTION_ERROR, 'Erreur : aucun email reçu', $id_d);
    }
}
