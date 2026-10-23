<?php

declare(strict_types=1);

namespace Pastell\Service\Document;

use ActionCreatorSQL;
use DocumentActionEntite;
use DocumentEmailSQL;
use DocumentEntiteSQL;
use DocumentSQL;
use Journal;
use NotificationMail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class DocumentEmailReadService
{
    private const ACTION_RECEPTION = 'reception';
    private const ACTION_PARTIAL_RECEPTION = 'reception-partielle';

    public function __construct(
        private readonly DocumentEmailSQL $documentEmailSQL,
        private readonly DocumentEntiteSQL $documentEntiteSQL,
        private readonly DocumentActionEntite $documentActionEntite,
        private readonly DocumentSQL $documentSQL,
        private readonly ActionCreatorSQL $actionCreatorSQL,
        private readonly Journal $journal,
        private readonly NotificationMail $notificationMail,
        private readonly string $site_base,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function read(string $key): ?array
    {
        $info = $this->documentEmailSQL->getByKey($key);
        if ($info === null || $info['non_recu']) {
            return null;
        }
        if ($info['lu']) {
            return $info;
        }
        $this->documentEmailSQL->setRead($key);

        $id_d = $info['id_d'];
        $id_e = $this->documentEntiteSQL->getEntiteId($id_d);

        $this->journal->addActionAutomatique(
            Journal::MAIL_SECURISE,
            $id_e,
            $id_d,
            'Consulté',
            $info['email'] . ' a consulté le document'
        );

        $allRead = $this->documentEmailSQL->isAllRead($id_d);
        $nextAction = $allRead ? self::ACTION_RECEPTION : self::ACTION_PARTIAL_RECEPTION;

        if ($this->documentActionEntite->getLastAction($id_e, $id_d) !== $nextAction) {
            $this->actionCreatorSQL->addAction(
                $id_e,
                0,
                $nextAction,
                $allRead ? 'Tous les destinataires ont consulté le message' : 'Un destinataire a consulté le message',
                $id_d
            );
        }

        $infoDocument = $this->documentSQL->getInfo($id_d);
        $message = "Le mail sécurisé {$infoDocument['titre']} a été consulté par {$info['email']}";
        if ($allRead) {
            $message .= "\n\nTous les destinataires ont consulté le message";
        }
        $message .= \sprintf(
            "\n\nConsulter le détail du document : %s/Document/detail?id_d=%s&id_e=%s",
            $this->site_base,
            $id_d,
            $id_e
        );
        $this->notificationMail->notify($id_e, $id_d, $nextAction, $infoDocument['type'], $message);

        return $this->documentEmailSQL->getByKey($key);
    }
}
