<?php

use Pastell\Mailer\Mailer;

class DocumentEmail extends SQL
{
    public const DESTINATAIRE = 'to';
    public const ID_DE = 'id_de';

    public static function getChaineTypeDestinataire($code)
    {
        $type = ['to' => 'Destinataire', 'cc' => 'Copie à' , 'bcc' => 'Copie caché à' ];
        return $type[$code];
    }

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly Journal $journal,
        private readonly NotificationMail $notificationMail,
        private readonly string $site_base,
    ) {
        parent::__construct($sqlQuery);
    }

    public function add($id_d, $email, $type)
    {
        $key = $this->getKey($id_d, $email);
        if ($key) {
            return $key;
        }
        $key = md5($id_d . $email . mt_rand());
        $sql = "INSERT INTO document_email(id_d,email,`key`,date_envoie,type_destinataire) VALUES (?,?,?,?,?)";
        $this->query($sql, $id_d, $email, $key, $this->getNow(), $type);
        return $key;
    }

    public function getKey($id_d, $email)
    {
        $sql = "SELECT `key` FROM document_email WHERE id_d=? AND email=?";
        return $this->queryOne($sql, $id_d, $email);
    }

    public function getInfo($id_d)
    {
        $sql = "SELECT * FROM document_email WHERE id_d=?";
        return $this->query($sql, $id_d);
    }

    public function getAllEmail($id_d): array
    {
        $sql = "SELECT email FROM document_email WHERE id_d=? ORDER BY email";
        return $this->queryOneCol($sql, $id_d);
    }

    public function getAllRecipientIds($id_d): array
    {
        $sql = "SELECT id_de FROM document_email WHERE id_d=? ORDER BY id_de";
        return $this->queryOneCol($sql, $id_d);
    }

    public function getInfoFromKey($key)
    {
        $sql = "SELECT * FROM document_email WHERE `key`=?";
        return $this->queryOne($sql, $key);
    }

    public function addError($id_de, $body)
    {
        $sql = "UPDATE document_email SET has_error=1,last_error=? WHERE id_de=?";
        $this->query($sql, $body, $id_de);

        $info = $this->getInfoFromPK($id_de);

        $sql = "SELECT count(*) as nb_total, count(has_error) as nb_error FROM document_email WHERE id_d=?";
        $count = $this->queryOne($sql, $info['id_d']);
        if ($count['nb_total'] != $count['nb_error']) {
            return;
        }

        $sql = "SELECT id_e FROM document_entite WHERE id_d=?";
        $id_e = $this->queryOne($sql, $info['id_d']);

        $documentActionEntite = new DocumentActionEntite($this->sqlQuery);
        $action = $documentActionEntite->getLastAction($id_e, $info['id_d']);
        $next_action = 'erreur';
        if ($action == $next_action) {
            return;
        }
        $actionCreator = new ActionCreator($this->sqlQuery, $this->journal, $info['id_d']);
        $actionCreator->addAction($id_e, 0, $next_action, "Erreur : aucun email reçu");
    }

    public function getId_e($id_d)
    {
        $sql = "SELECT id_e FROM document_entite WHERE id_d=?";
        return $this->queryOne($sql, $id_d);
    }

    public function consulter($key, Journal $journal)
    {
        $result = $this->getInfoFromKey($key);
        if ((! $result) || $result['non_recu']) {
            return false;
        }
        if ($result['lu']) {
            return $result;
        }
        $sql = "UPDATE document_email SET lu=1,date_lecture=? WHERE `key` = ?";
        $this->query($sql, $this->getNow(), $key);

        $sql = "SELECT id_e FROM document_entite WHERE id_d=?";
        $id_e = $this->queryOne($sql, $result['id_d']);

        $journal->addActionAutomatique(
            Journal::MAIL_SECURISE,
            $id_e,
            $result['id_d'],
            'Consulté',
            $result['email'] . " a consulté le document"
        );
        $sql = "SELECT count(*) as nb_total,sum(lu) as nb_lu FROM document_email WHERE id_d=?";
        $count = $this->queryOne($sql, $result['id_d']);

        if ($count['nb_lu'] == $count['nb_total']) {
            $next_action = 'reception';
        } else {
            $next_action = 'reception-partielle';
        }

        $documentActionEntite = new DocumentActionEntite($this->sqlQuery);
        $action = $documentActionEntite->getLastAction($id_e, $result['id_d']);


        $message_action = ($next_action == 'reception') ? "Tous les destinataires ont consulté le message" : "Un destinataire a consulté le message";
        if ($action != $next_action) {
            $actionCreator = new ActionCreator($this->sqlQuery, $journal, $result['id_d']);
            $actionCreator->addAction($id_e, 0, $next_action, $message_action);
        }

        $document = new DocumentSQL($this->sqlQuery, new PasswordGenerator());
        $infoDocument = $document->getInfo($result['id_d']);


        $message = "Le mail sécurisé {$infoDocument['titre']} a été consulté par {$result['email']}";
        if ($next_action == 'reception') {
            $message .= "\n\nTous les destinataires ont consulté le message";
        }
        $message .= \sprintf(
            "\n\nConsulter le détail du document : %s/Document/detail?id_d=%s&id_e=%s",
            $this->site_base,
            $result['id_d'],
            $id_e
        );

        $this->notificationMail->notify($id_e, $result['id_d'], $next_action, $infoDocument['type'], $message);

        return $this->getInfoFromKey($key);
    }

    public function getInfoFromPK($id_de)
    {
        $sql = "SELECT * FROM document_email WHERE id_de=?";
        return $this->queryOne($sql, $id_de);
    }

    public function updateRenvoi($id_de)
    {
        $sql = <<<SQL
UPDATE document_email 
SET date_renvoi=?, nb_renvoi=nb_renvoi+1 
WHERE id_de=?
SQL;
        $this->query($sql, $this->getNow(), $id_de);
    }

    public function addReponse($id_de, $reponse)
    {
        $sql = "UPDATE document_email SET reponse=? WHERE id_de=?";
        $this->query($sql, $reponse, $id_de);
    }

    public function getNumberOfMailRead(string $id_d): int
    {
        $sql = <<<EOT
SELECT count(*)
FROM document_email
WHERE id_d = ?
 AND lu = 1;
EOT;

        return $this->queryOne($sql, $id_d);
    }

    public function addEtatNonRecu($id_d): void
    {
        $sql = "UPDATE document_email SET non_recu=1 WHERE id_d=? and lu=0";
        $this->query($sql, $id_d);
    }
}
