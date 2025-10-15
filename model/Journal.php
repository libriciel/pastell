<?php

use Aws\S3\Exception\S3Exception;
use Monolog\Logger;
use Pastell\Storage\StorageInterface;

class Journal extends SQL
{
    public const DOCUMENT_ACTION = 1;
    public const NOTIFICATION = 2;
    public const MODIFICATION_ENTITE = 3;
    public const MODIFICATION_UTILISATEUR = 4;
    public const MAIL_SECURISE = 5;
    public const CONNEXION = 6;
    public const DOCUMENT_CONSULTATION = 7 ;
    public const ENVOI_MAIL = 8;
    public const DOCUMENT_ACTION_ERROR = 9;
    public const DOCUMENT_TRAITEMENT_LOT = 10;
    public const TEST = 11;
    public const TYPE_DOSSIER_EDITION = 12;
    public const JOURNAL = 13;
    public const COMMANDE = 14;

    public const DEFAULT_LIMIT = 100;

    public const NO_ID_D = '';
    public const ACTION_SUPPRIME = 'Supprimé';
    public const ACTION_MODIFFIE = 'Modifié';
    public const ACTION_AJOUTE = 'Ajouté';
    public const ACTION_CREATED = 'Créé';

    private $id_u;
    private Horodateur $horodateur;

    public function __construct(
        SQLQuery $sqlQuery,
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly Logger $logger,
        private readonly bool $disable_journal_horodatage,
        private readonly bool $use_external_storage_for_journal_proof,
        private StorageInterface $storage,
    ) {
        parent::__construct($sqlQuery);
    }

    public function setInterfaceStorage(StorageInterface $storageInterface): void
    {
        $this->storage = $storageInterface;
    }
    public function setHorodateur(Horodateur $horodateur)
    {
        $this->horodateur = $horodateur;
    }

    public function setId($id_u)
    {
        $this->id_u = $id_u;
    }

    public function addConsultation($id_e, $id_d, $id_u)
    {
        $sql  =  "SELECT count(*) FROM journal WHERE id_u=? AND id_d=?";
        $nb = $this->queryOne($sql, $id_u, $id_d);
        if ($nb) {
            return false;
        }
        $infoUtilisateur = $this->utilisateurSQL->getInfo($id_u);
        $nom = $infoUtilisateur['prenom'] . " " . $infoUtilisateur['nom'];
        return $this->add(Journal::DOCUMENT_CONSULTATION, $id_e, $id_d, "Consulté", "$nom a consulté le dossier");
    }

    public function add($type_journal, $id_e, $id_d, $action, $message)
    {
        return $this->addSQL($type_journal, $id_e, $this->id_u, $id_d, $action, $message);
    }

    public function addActionAutomatique($type, $id_e, $id_d, $action, $message)
    {
        return $this->addSQL($type, $id_e, 0, $id_d, $action, $message);
    }

    public function addSQL($type, $id_e, $id_u, $id_d, $action, $message)
    {
        if ($id_d) {
            $document_info = $this->documentSQL->getInfo($id_d);
            $document_type = $document_info['type'] ?? "";
        } else {
            $document_type = "";
            $id_d = 0;
        }
        if (!$id_e) {
            $id_e = "0";
        }
        if (! $action) {
            $action = "";
        }

        $now = $this->getNow();
        $message_horodate = "$type - $id_e - $id_u - $id_d - $action - $message - $now - $document_type";

        $preuve = "";
        $date_horodatage = "";

        if (
            (isset($this->horodateur))  &&
            (! $this->disable_journal_horodatage)
        ) {
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
            $sql = "INSERT INTO journal(type,id_e,id_u,id_d,action,message,date,message_horodate,date_horodatage,document_type, preuve) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
            $this->query($sql, $type, $id_e, $id_u, $id_d, $action, $message, $now, $message_horodate, $date_horodatage, $document_type, $preuve);

            $id_j = $this->lastInsertId();
        } else {
            $sql = "INSERT INTO journal(type,id_e,id_u,id_d,action,message,date,message_horodate,date_horodatage,document_type) VALUES (?,?,?,?,?,?,?,?,?,?)";
            $this->query($sql, $type, $id_e, $id_u, $id_d, $action, $message, $now, $message_horodate, $date_horodatage, $document_type);

            $id_j = $this->lastInsertId();

            $this->saveProof($id_j, $preuve);
        }

        if (
            (! $preuve) &&
            (! $this->disable_journal_horodatage)
        ) {
            $sql = "INSERT INTO journal_attente_preuve (id_j) VALUES (?)";
            $this->query($sql, $id_j);
        }

        $this->logger->info("Ajout au journal (id_j=$id_j): " . $message_horodate);

        return $id_j;
    }
    public function saveProof(int $id_j, string $preuve): void
    {
        $this->storage->write($id_j . 'preuve.tsa', $preuve);
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

    public function getAll(
        $id_e = false,
        $type = false,
        $id_d = false,
        $id_u = false,
        $offset = 0,
        $limit = self::DEFAULT_LIMIT,
        $recherche = "",
        $date_debut = false,
        $date_fin = false,
        $tri_croissant = false,
        $with_preuve = true
    ) {
        [$sql,$value] = $this->getQueryAll($id_e, $type, $id_d, $id_u, $offset, $limit, $recherche, $date_debut, $date_fin, $tri_croissant);

        $result = $this->query($sql, $value);
        foreach ($result as $i => $line) {
            $documentType = $this->documentTypeFactory->getFluxDocumentType($line['document_type']);
            $result[$i]['document_type_libelle'] = $documentType->getName();
            $result[$i]['action_libelle'] = $documentType->getAction()->getActionName($line['action']);
            if (!$with_preuve) {
                unset($result[$i]['preuve']);
            } elseif ($result[$i]['preuve'] === '' && $this->use_external_storage_for_journal_proof) {
                $result[$i]['preuve'] = $this->getProof($result[$i]['id_j']);
            }
        }
        return $result;
    }

    public function getQueryAll($id_e, $type, $id_d, $id_u, $offset, $limit, $recherche = "", $date_debut = false, $date_fin = false, $tri_croissant = false)
    {
        $value = [];
        $sql = "SELECT journal.*,document.titre,entite.denomination, utilisateur.nom, utilisateur.prenom,entite.siren " .
            " FROM journal " .
            " LEFT JOIN document ON journal.id_d = document.id_d " .
            " LEFT JOIN entite ON journal.id_e = entite.id_e " .
            " LEFT JOIN utilisateur ON journal.id_u = utilisateur.id_u " .
            " WHERE 1=1 ";

        if ($id_e) {
            $sql .= "AND journal.id_e = ? ";
            $value[] = $id_e;
        }
        if ($type) {
            $sql .= " AND document_type=?";
            $value[] = $type;
        }
        if ($id_d) {
            $sql .= " AND journal.id_d = ? ";
            $value[] = $id_d;
        }
        if ($id_u) {
            $sql .= " AND journal.id_u = ? ";
            $value[] = $id_u;
        }
        if ($recherche) {
            $sql .= " AND journal.message_horodate LIKE ?";
            $value[] = "%$recherche%";
        }
        if ($date_debut) {
            $sql .= "AND DATE(journal.date) >= ?";
            $value[] = $date_debut;
        }
        if ($date_fin) {
            $sql .= "AND DATE(journal.date) <= ?";
            $value[] = $date_fin;
        }
        if ($tri_croissant == true) {
            $order_direction = "ASC";
        } else {
            $order_direction = "DESC";
        }
        $sql .= " ORDER BY id_j " . $order_direction;
        if ($limit != -1) {
            $sql .= " LIMIT $offset,$limit";
        }
        return [$sql,$value];
    }



    public function countAll($id_e, $type, $id_d, $id_u, $recherche, $date_debut, $date_fin)
    {
        $join = "";
        $where = [];
        $value = [];

        if ($id_e) {
            $where[] = " id_e = ?";
            $value[] = $id_e;
        }
        if ($type) {
            $where[] = " document_type=?";
            $value[] = $type;
        }
        if ($id_d) {
            $where[] = " journal.id_d = ? ";
            $value[] = $id_d;
        }
        if ($id_u) {
            $where[] = " journal.id_u = ? ";
            $value[] = $id_u;
        }
        if ($recherche) {
            $where[] = " journal.message_horodate LIKE ? ";
            $value[] = "%$recherche%";
        }
        if ($date_debut) {
            $where[] = " DATE(journal.date) >= ? ";
            $value[] = $date_debut;
        }
        if ($date_fin) {
            $where[] = " DATE(journal.date) <= ? ";
            $value[] = $date_fin;
        }

        $where = implode(" AND ", $where);
        if ($where) {
            $where = " WHERE $where ";
        }

        $sql = "SELECT count(*) FROM journal $join $where ";

        return $this->queryOne($sql, $value);
    }


    public function getTypeAsString($type)
    {
        $type_string = [1 => "Action sur un dossier",
                        "Notification",
                        "Gestion des entités",
                        "Gestion des utilisateurs",
                        "Mail sécurisé",
                        "Connexion",
                        "Consultation de dossier ou de document",
                        "Envoi de mail",
                        "Erreur lors de la tentative d'une action",
                        "Programmation d'un traitement par lot",
                        "Test",
                        "Action sur un type de dossier personnalisé",
                        "Action sur le journal",
                        "Action par commande"
        ];
        return $type_string[$type];
    }

    public function getInfo($id_j)
    {
        $sql = "SELECT * FROM journal WHERE id_j=?";
        $result = $this->queryOne($sql, $id_j);

        if ($result['preuve'] === '' && $this->use_external_storage_for_journal_proof) {
            $result['preuve'] = $this->getProof($result['id_j']);
        }

        return $result;
    }

    public function getAllInfo($id_j)
    {
        $sql = "SELECT journal.*,document.titre,entite.denomination, utilisateur.nom, utilisateur.prenom FROM journal " .
            " LEFT JOIN document ON journal.id_d = document.id_d " .
            " LEFT JOIN entite ON journal.id_e = entite.id_e " .
            " LEFT JOIN utilisateur ON journal.id_u = utilisateur.id_u " .
            " WHERE id_j=?";
        $result = $this->queryOne($sql, $id_j);

        if (!$result) {
            return $result;
        }

        $documentType = $this->documentTypeFactory->getFluxDocumentType($result['document_type']);
        $result['document_type_libelle'] = $documentType->getName();
        $result['action_libelle'] = $documentType->getAction()->getActionName($result['action']);

        if ($result['preuve'] === '' && $this->use_external_storage_for_journal_proof) {
            $result['preuve'] = $this->getProof($id_j);
        }

        return $result;
    }

    public function horodateAll()
    {
        if (! isset($this->horodateur)) {
            throw new Exception("Aucun horodateur configuré\n");
        }

        $sql = "SELECT id_j FROM journal_attente_preuve";
        $id_j_list = $this->queryOneCol($sql);

        if (!$this->use_external_storage_for_journal_proof) {
            $sql = "UPDATE journal set preuve=?,date_horodatage=? WHERE id_j=?";
        } else {
            $sql = "UPDATE journal set date_horodatage=? WHERE id_j=?";
        }
        $sql2 = "DELETE FROM journal_attente_preuve WHERE id_j=?";

        foreach ($id_j_list as $id_j) {
            $info = $this->getInfo($id_j);
            $preuve = $this->horodateur->getTimestampReply($info['message_horodate']);
            $date_horodatage = $this->horodateur->getTimeStamp($preuve);
            if (!$this->use_external_storage_for_journal_proof) {
                $this->query($sql, $preuve, $date_horodatage, $info['id_j']);
            } else {
                $this->saveProof($id_j, $preuve);
                $this->query($sql, $date_horodatage, $info['id_j']);
            }
            echo "{$info['id_j']} horodaté : $date_horodatage\n";
            $this->query($sql2, $id_j);
        }
    }

    public function getNbLine()
    {
        return $this->queryOne("SELECT count(*) FROM journal;");
    }

    public function getNbLineHistorique()
    {
        return $this->queryOne("SELECT count(*) FROM journal_historique;");
    }

    public function getFirstLineDate()
    {
        return $this->queryOne("SELECT date FROM journal ORDER BY id_j LIMIT 1;");
    }

    public function purgeToHistorique($journal_max_age_in_months = 2)
    {
        $date = date("Y-m-d H:i:s", strtotime("-$journal_max_age_in_months months"));
        $sql = "SELECT id_j FROM journal WHERE date<? ORDER BY date LIMIT 1000";

        do {
            $id_j_list = $this->queryOneCol($sql, $date);
            $sql_verify = "SELECT count(*) FROM journal_historique WHERE id_j = ?";
            $sql_insert = "INSERT INTO journal_historique SELECT * FROM journal WHERE id_j=?";
            $sql_delete = "DELETE FROM journal WHERE id_j=?";

            foreach ($id_j_list as $id_j) {
                $this->logger->debug("Purge de l'enregitrement id_j $id_j");
                if (! $this->queryOne($sql_verify, $id_j)) {
                    $this->query($sql_insert, $id_j);
                }
                $this->query($sql_delete, $id_j);
            }
        } while ($id_j_list);
        return true;
    }
}
