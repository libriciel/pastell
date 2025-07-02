<?php

abstract class TdtConnecteur extends Connecteur
{
    public const FAMILLE_CONNECTEUR = 'TdT';

    public const STATUS_ERREUR = -1;
    public const STATUS_ANNULE = 0;
    public const STATUS_POSTE = 1;
    public const STATUS_EN_ATTENTE_DE_TRANSMISSION = 2;
    public const STATUS_TRANSMIS = 3;
    public const STATUS_ACQUITTEMENT_RECU = 4;
    public const STATUS_VALIDE = 5;
    public const STATUS_REFUSE = 6;

    public const STATUS_HELIOS_TRAITEMENT = 7;
    public const STATUS_HELIOS_INFO = 8;
    public const STATUS_HELIOS_ATTENTE = 9;

    public const STATUS_ACTES_MESSAGE_PREF_RECU = 7;
    public const STATUS_ACTES_MESSAGE_PREF_RECU_AR = 8;
    public const STATUS_ACTES_MESSAGE_PREF_RECU_PAS_D_AR = 21;

    public const STATUS_ACTES_MESSAGE_PREF_ACQUITTEMENT_RECU = 11;

    public const STATUS_ACTES_EN_ATTENTE_DE_POSTER = 17;


    public const COURRIER_SIMPLE = 2;
    public const DEMANDE_PIECE_COMPLEMENTAIRE = 3;
    public const LETTRE_OBSERVATION = 4;
    public const DEFERE_TRIBUNAL_ADMINISTRATIF = 5;

    private $arActes;

    public static function getStatusString($status)
    {
        $statusString = [
            -1 => 'Erreur',
            0 => 'Annulé',
            1 => 'Posté',
            2 => 'En attente de transmission',
            3 => 'Transmis',
            4 => 'Acquittement reçu',
            5 => 'Validé',
            6 => 'Refusé',
            7 => 'AR non disponible pour le moment',
            17 => "En attente d'être postée"
        ];
        if (empty($statusString[$status])) {
            return "Statut inconnu ($status)";
        }
        return $statusString[$status];
    }

    public static function getTransactionNameFromNumber($type_reponse)
    {
        $transactionsName = [
            self::COURRIER_SIMPLE => 'Courrier simple',
            self::DEMANDE_PIECE_COMPLEMENTAIRE => 'Demande de pièces complémentaires',
            self::LETTRE_OBSERVATION => "Lettre d'observation",
            self::DEFERE_TRIBUNAL_ADMINISTRATIF => 'Déféré au tribunal administratif'
        ];

        return $transactionsName[$type_reponse] ?? "Transaction inconnue ($type_reponse)";
    }

    abstract public function getLogicielName();

    abstract public function testConnexion();

    abstract public function getClassification();

    abstract public function demandeClassification();

    abstract public function annulationActes($id_transaction);

    abstract public function verifClassif();

    abstract public function sendHelios(Fichier $fichierHelios);

    abstract public function sendActes(TdtActes $tdtActes);

    abstract public function getStatusHelios($id_transaction);

    abstract public function getStatus($id_transaction);

    abstract public function getLastReponseFile();

    abstract public function getDateAR($id_transaction);

    abstract public function getBordereau($id_transaction);

    abstract public function getActeTamponne(string $id_transaction, ?string $date_affichage = null): ?string;

    abstract public function getFichierRetour($transaction_id);

    abstract public function getListReponsePrefecture($transaction_id);

    abstract public function getReponsePrefecture($transaction_id);

    abstract public function sendResponse(DonneesFormulaire $donneesFormulaire);

    abstract public function getAnnexesTamponnees(string $transaction_id, ?string $date_affichage = null): array;

    abstract public function getFilenameTransformation(string $filename);

    /* URL pour rediriger l'utilisateur et ainsi permettre qu'il puisse s'authentifier avec un certificat RGS** */
    public function getRedirectURLForTeletransimission()
    {
    }

    /* Idem en version "par lot" */
    public function getRedirectURLForTeletransimissionMulti()
    {
    }

    /* Permet de récupérer un nonce de S2low pour l'authentification par nonce */
    public function getNounce()
    {
    }

    /**
     * @param string $nature
     * @param string $classification_file_path The path to the classification file
     * @return mixed
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function getDefaultTypology($nature, $classification_file_path)
    {
        $actesTypePJ = new ActesTypePJ();

        $actesTypePJData = new ActesTypePJData();

        $actesTypePJData->acte_nature = $nature;
        $actesTypePJData->classification_file_path = $classification_file_path;

        $piece_list = $actesTypePJ->getTypePJListe($actesTypePJData);

        if (!$piece_list) {
            throw new UnrecoverableException(
                "Impossible de trouver un typage par défaut pour la nature $nature"
            );
        }

        return array_keys($piece_list)[0];
    }

    /**
     * @param string $natureActe
     * @return string
     * @throws Exception
     */
    public function getShortenedNatureActe(string $natureActe): string
    {
        $shortenedNatureActe = [
            '1' => 'DE',
            '2' => 'AR',
            '3' => 'AI',
            '4' => 'CC',
            '5' => 'BF',
            '6' => 'AU'
        ];
        if (!array_key_exists($natureActe, $shortenedNatureActe)) {
            throw new Exception("La nature $natureActe est inconnue.");
        }
        return $shortenedNatureActe[$natureActe];
    }

    /**
     * @param string $shortenedNatureActe
     * @return int
     * @throws Exception
     */
    public function getIntNatureActe(string $shortenedNatureActe): int
    {
        $intNatureActe = [
            'DE' => 1,
            'AR' => 2,
            'AI' => 3,
            'CC' => 4,
            'BF' => 5,
            'AU' => 6
        ];
        if (!array_key_exists($shortenedNatureActe, $intNatureActe)) {
            throw new Exception("La nature $shortenedNatureActe est inconnue.");
        }
        return $intNatureActe[$shortenedNatureActe];
    }

    public function getARActes()
    {
        return $this->arActes;
    }

    /**
     * @param mixed $arActes
     */
    public function setArActes($arActes)
    {
        $this->arActes = $arActes;
    }

    public function getStatusInfo($status_id)
    {
        //Note : les status helios et actes sont commun sur le TdT pour la plupart.
        $all_status = [
            -1 => "Erreur",
            0 => "Annulé",
            1 => "Posté",
            2 => "En attente de transmission. Fichier valide.",
            3 => "Transmis",
            4 => "Acquittement reçu",
            5 => "status 5 invalide",
            6 => "Refusé",
            7 => "En traitement",
            8 => "Information disponible"
        ];
        if (empty($all_status[$status_id])) {
            return "Status $status_id inconnu sur Pastell";
        }
        return $all_status[$status_id];
    }

    /**
     * @return array
     */
    public function getReponsePrefectureFlux(): array
    {
        return [
            TdtConnecteur::COURRIER_SIMPLE,
            TdtConnecteur::DEMANDE_PIECE_COMPLEMENTAIRE,
            TdtConnecteur::LETTRE_OBSERVATION
        ];
    }
}
