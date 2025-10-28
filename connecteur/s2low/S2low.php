<?php

declare(strict_types=1);

use Pastell\Service\Document\DocumentTitre;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class S2low extends TdtConnecteur
{
    public const string URL_TEST = '/api/test-connexion.php';
    public const string URL_GET_NOUNCE = '/api/get-nounce.php';
    public const string URL_CLASSIFICATION = '/modules/actes/actes_classification_fetch.php';
    public const string URL_POST_ACTES = '/modules/actes/actes_transac_create.php';
    public const string URL_STATUS = '/modules/actes/actes_transac_get_status.php';
    public const string URL_ANNULATION = '/modules/actes/actes_transac_cancel.php';
    public const string URL_BORDEREAU = '/modules/actes/actes_create_pdf.php';
    public const string URL_DEMANDE_CLASSIFICATION = '/modules/actes/actes_classification_request.php';
    public const string URL_POST_HELIOS = '/modules/helios/api/helios_importer_fichier.php';
    public const string URL_STATUS_HELIOS = '/modules/helios/api/helios_transac_get_status.php';
    public const string URL_HELIOS_RETOUR = '/modules/helios/helios_download_acquit.php';
    public const string URL_LIST_LOGIN = '/admin/users/api-list-login.php';
    public const string URL_ACTES_REPONSE_PREFECTURE = '/modules/actes/actes_transac_get_document.php';
    public const string URL_ACTES_REPONSE_PREFECTURE_LISTE = '/modules/actes/api/list_document_prefecture.php';
    public const string URL_ACTES_REPONSE_PREFECTURE_MARK_AS_READ = '/modules/actes/api/document_prefecture_mark_as_read.php';
    public const string URL_POST_REPONSE_PREFECTURE = '/modules/actes/actes_transac_reponse_create.php';
    public const string URL_POST_CONFIRM = '/modules/actes/actes_transac_post_confirm_api.php';
    public const string URL_POST_CONFIRM_MULTI = '/modules/actes/actes_transac_post_confirm_api_multi.php';

    public const string URL_HELIOS_PES_RETOUR_LISTE = '/modules/helios/api/helios_get_list.php';
    public const string URL_HELIOS_PES_RETOUR_UPDATE = '/modules/helios/api/helios_change_status.php';
    public const string URL_HELIOS_PES_RETOUR_GET = '/modules/helios/api/helios_get_retour.php';

    public const string URL_GET_FILE_LIST = '/modules/actes/actes_transac_get_files_list.php';
    public const string URL_DOWNLOAD_FILE = '/modules/actes/actes_download_file.php';

    public const string FLUX_PES_RETOUR = 'helios-pes-retour';
    public const string FLUX_REPONSE_PREFECTURE = 'actes-reponse-prefecture';

    private string $reponseFile;
    private CurlWrapperFactory $curlWrapperFactory;
    protected CurlWrapper $curlWrapper;
    protected bool $ensureLogin = false;
    protected bool $enAttente;
    protected bool $authenticationForTeletransmisson;
    protected bool $specialHeaderAdded;
    protected string $userLogin;
    protected string $userPassword;
    protected string $tedetisURL;
    protected string $classificationFilePath;

    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
    ) {
        $this->curlWrapperFactory = $this->objectInstancier->getInstance(CurlWrapperFactory::class);
    }

    /**
     * @throws UnrecoverableException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $this->curlWrapper = $this->curlWrapperFactory->getInstance();
        $this->specialHeaderAdded = false;
        $this->curlWrapper->setServerCertificate($donneesFormulaire->getFilePath('server_certificate'));
        $this->curlWrapper->dontVerifySSLCACert();
        $this->curlWrapper->setClientCertificate(
            $donneesFormulaire->getFilePath('user_certificat_pem'),
            $donneesFormulaire->getFilePath('user_key_pem'),
            $donneesFormulaire->get('user_certificat_password')
        );
        $this->userLogin = (string)$donneesFormulaire->get('user_login');
        $this->userPassword = (string)$donneesFormulaire->get('user_password');

        if ($this->userLogin !== '') {
            $this->curlWrapper->httpAuthentication(
                $this->userLogin,
                $this->userPassword
            );
            $this->ensureLogin = true;
        }
        $this->tedetisURL = (string)$donneesFormulaire->get('url');
        $this->classificationFilePath = $donneesFormulaire->getFilePath('classification_file');
        $this->enAttente = (bool)$donneesFormulaire->get('envoi_en_attente');
        $this->authenticationForTeletransmisson = (bool)$donneesFormulaire->get('authentication_for_teletransmisson');
    }

    /**
     * @throws S2lowException
     */
    protected function ensureLogin(): bool
    {
        if ($this->ensureLogin) {
            return true;
        }

        $output = $this->curlWrapper->get($this->tedetisURL . self::URL_LIST_LOGIN);

        if ($this->curlWrapper->getLastError()) {
            throw new S2lowException($this->curlWrapper->getLastError());
        }

        if ($output) {
            $this->ensureLogin = true;
            return true;
        }
        throw new S2lowException('La connexion S²low nécessite un login/mot de passe ');
    }

    /**
     * @throws S2lowException
     */
    private function exec($url, bool $utf_8_encode = true): bool|string
    {
        $this->ensureLogin();
        $output = $this->curlWrapper->get($this->tedetisURL . $url);
        $error = $this->curlWrapper->getLastError();

        if (! $output && $error) {
            throw new S2lowException($error);
        }
        if ($utf_8_encode) {
            $output = mb_convert_encoding((string)$output, 'UTF-8', 'ISO-8859-1');
        }
        return $output;
    }

    public function getLogicielName(): string
    {
        return 'S²low';
    }

    /**
     * @throws S2lowException
     */
    public function testConnexion(): void
    {
        $result = $this->exec(self::URL_TEST);
        if (!str_starts_with($result, 'OK')) {
            throw new S2lowException('Erreur lors de la tentative de connexion, S²low a répondu : ' . $result);
        }
    }

    /**
     * @throws S2lowException
     */
    public function getClassification(): bool|string
    {
        $result = $this->exec(self::URL_CLASSIFICATION . '?api=1', false);
        if (!$result) {
            throw new S2lowException($this->curlWrapper->getLastError());
        }
        if (str_starts_with($result, 'KO')) {
            throw new S2lowException('S²low a répondu : ' . mb_convert_encoding($result, 'UTF-8', 'ISO-8859-1'));
        }
        return $result;
    }

    /**
     * @throws S2lowException
     */
    public function demandeClassification(): string
    {
        $result = $this->exec(self::URL_DEMANDE_CLASSIFICATION . '?api=1');
        $message = 'S²low a répondu : ' . $result;
        if (str_starts_with($result, 'KO')) {
            throw new S2lowException($message);
        }
        return $message;
    }

    /**
     * @throws S2lowException
     */
    public function annulationActes($id_transaction): string
    {
        $this->curlWrapper->addPostData('api', 1);
        $this->curlWrapper->addPostData('id', $id_transaction);
        $result = $this->exec(self::URL_ANNULATION);
        if (! $result) {
            throw new S2lowException('Erreur lors de la connexion a S²low (' . $this->tedetisURL . ')');
        }

        if (!str_starts_with($result, 'OK')) {
            throw new S2lowException('Erreur lors de la transmission, S²low a répondu : ' . $result);
        }
        $ligne = explode("\n", $result);
        return trim($ligne[1]); // id_transaction
    }

    /**
     * @throws S2lowException
     */
    public function verifClassif(): bool
    {
        if (! is_file($this->classificationFilePath)) {
            throw new S2lowException("Il n'y a pas de fichier de classification Actes");
        }

        $usingClassif = file_get_contents($this->classificationFilePath);
        $theClassif = $this->getClassification();

        if ($usingClassif !== $theClassif) {
            throw new S2lowException("La classification utilisée n'est plus à jour");
        }
        return true;
    }

    /**
     * @param Fichier $fichierHelios
     * @return SimpleXMLElement
     * @throws S2lowException
     */
    public function sendHelios(Fichier $fichierHelios)
    {
        $file_path = $fichierHelios->filepath;
        $file_name = $fichierHelios->filename ?: '';
        $file_name = $this->getHeliosEnveloppeFileName($file_name);
        $this->curlWrapper->addPostFile('enveloppe', $file_path, $file_name);
        $result = $this->exec(self::URL_POST_HELIOS);

        $simpleXMLWrapper = new SimpleXMLWrapper();
        try {
            $xml = $simpleXMLWrapper->loadString($result);
        } catch (SimpleXMLWrapperException $e) {
            throw new S2lowException("La réponse de S²low n'a pas pu être analysée : " . get_hecho($result));
        }

        if ($xml->{'resultat'} != 'OK') {
            throw new S2lowException("Erreur lors de l'envoi du PES : " . $xml->{'message'});
        }
        return $xml->{'id'};
    }

    public function getHeliosEnveloppeFileName(?string $name): string
    {
        return preg_replace('#[^a-zA-Z0-9._\- ]#', '_', $name);
    }


    private function getIsEnAttente(): int
    {
        if ($this->enAttente || $this->authenticationForTeletransmisson) {
            return 1;
        }
        return 0;
    }

    /**
     * @throws S2lowException
     */
    public function sendActes(TdtActes $tdtActes): string
    {
        $this->verifClassif();

        $this->curlWrapper->addPostData('api', 1);
        $this->curlWrapper->addPostData('nature_code', $tdtActes->acte_nature);

        $this->curlWrapper->addPostData('number', $tdtActes->numero_de_lacte);
        $this->curlWrapper->addPostData('subject', mb_convert_encoding($tdtActes->objet, 'ISO-8859-1'));

        $this->curlWrapper->addPostData('decision_date', date("Y-m-d", strtotime($tdtActes->date_de_lacte)));
        $this->curlWrapper->addPostData('en_attente', $this->getIsEnAttente());

        $this->curlWrapper->addPostData('document_papier', $tdtActes->document_papier ? 1 : 0);

        if ($tdtActes->type_acte) {
            $this->curlWrapper->addPostData('type_acte', $tdtActes->type_acte);
        }
        if ($tdtActes->type_pj) {
            foreach (json_decode($tdtActes->type_pj) as $type_pj) {
                $this->curlWrapper->addPostData('type_pj[]', $type_pj);
            }
        }

        $file_path = $tdtActes->arrete->filepath;
        $file_name = $tdtActes->arrete->filename;

        $file_name = $this->getFilenameTransformation($file_name);
        $this->curlWrapper->addPostFile('acte_pdf_file', $file_path, $file_name);

        if ($tdtActes->autre_document_attache) {
            foreach ($tdtActes->autre_document_attache as $i => $annexe_file) {
                $file_name = $this->getFilenameTransformation($annexe_file->filename);
                $file_path = $annexe_file->filepath;
                $this->curlWrapper->addPostFile('acte_attachments[]', $file_path, $file_name) ;
            }
        }

        $classification  = $tdtActes->classification;
        $c1 = explode(' ', $classification);
        $dataClassif = explode('.', $c1[0]);

        foreach ($dataClassif as $i => $elementClassif) {
            $this->curlWrapper->addPostData('classif' . ( $i + 1), $elementClassif);
        }

        $result = $this->exec(self::URL_POST_ACTES);
        if (! $result) {
            throw new S2lowException('Erreur lors de la connexion à S²low (' . $this->tedetisURL . ')');
        }

        if (!str_starts_with($result, 'OK')) {
            throw new S2lowException('Erreur lors de la transmission, S²low a répondu : ' . $result);
        }

        $ligne = explode("\n", $result);
        return trim($ligne[1]);
    }

    /**
     * @throws S2lowException
     */
    public function getStatusHelios($id_transaction): string
    {
        $result = $this->exec(self::URL_STATUS_HELIOS . "?transaction=$id_transaction", false);
        $simpleXMLWrapper = new SimpleXMLWrapper();
        try {
            $xml = $simpleXMLWrapper->loadString($result);
        } catch (SimpleXMLWrapperException $e) {
            throw new S2lowException(
                "La réponse de S²low n'a pas pu être analysée (problème d'authentification ?)"
            );
        }

        if ($xml->{'resultat'} == 'KO') {
            throw new S2lowException((string)$xml->{'message'});
        }
        $this->reponseFile = $result;
        return (string)$xml->{'status'};
    }

    /**
     * @throws S2lowException
     */
    public function getStatus($id_transaction): bool|string
    {
        $result = $this->exec(self::URL_STATUS . "?transaction=$id_transaction");

        $ligne = explode("\n", $result);

        if (trim($ligne[0]) !== 'OK') {
            throw new S2lowException(trim($ligne[1]));
        }

        $result = trim($ligne[1]);
        if ($result == 4) {
            array_shift($ligne);
            array_shift($ligne);
            $this->setArActes(mb_convert_encoding(implode("\n", $ligne), 'ISO-8859-1'));
        }

        if ($result == -1) {
            array_shift($ligne);
            array_shift($ligne);
            $this->lastError = implode("\n", $ligne);
        }
        return $result;
    }

    /**
     * @throws Exception
     * @throws S2lowException
     */
    // Pour test:
    // http://simulateurhelios.formations.adullact.org/index.php/Accueil/index/
    // L'ADULLACT, SIRET : 96848903944889
    public function getPESRetourListe(): bool
    {
        //get PES Retour non lu
        $pes = [];
        $result = $this->exec(self::URL_HELIOS_PES_RETOUR_LISTE);
        $xml = @ simplexml_load_string($result);
        if (! $xml) {
            throw new S2lowException("La réponse de S²low n'a pas pu être analysée : (" . $result . ')');
        }
        if (!empty($xml->pes_retour)) {
            foreach ($xml->pes_retour as $pes_retour) {
                $pes['id'] = (string)$pes_retour->{'id'};
                $pes['nom'] = (string)$pes_retour->{'nom'};
                $pes['date'] = (string)$pes_retour->{'date'};
                $this->getPESRetour($pes);
            }
            return true;
        }
        throw new S2lowException('S2low ne retourne pas de PES Retour');
    }

    /**
     * @throws Exception
     * @throws S2lowException
     * @throws TransportExceptionInterface
     */
    public function getPESRetour(array $pes = []): bool|string
    {
        // création document flux helios PES Retour non lu

        $connecteur_info = $this->getConnecteurInfo();
        $id_e = $connecteur_info['id_e'];

        $fic_pes = $this->exec(self::URL_HELIOS_PES_RETOUR_GET . '?id=' . $pes['id'], false);

        /** @var DocumentTypeFactory $documentTypeFactory */
        $documentTypeFactory = $this->objectInstancier->getInstance(DocumentTypeFactory::class);
        if (! $documentTypeFactory->isTypePresent(self::FLUX_PES_RETOUR)) {
            throw new Exception('Le type ' . self::FLUX_PES_RETOUR . " n'existe pas sur cette plateforme Pastell");
        }

        $documentCreationService = $this->objectInstancier->getInstance(DocumentCreationService::class);
        $new_id_d = $documentCreationService->createDocumentWithoutAuthorizationChecking($id_e, self::FLUX_PES_RETOUR);

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)
            ->get($new_id_d);

        $nom_pes = $pes['nom'];
        if (!str_ends_with($nom_pes, '.xml')) {
            return "$nom_pes n'est pas un fichier xml";
        }
        $donneesFormulaire->setData('objet', substr($nom_pes, 0, -4));
        $donneesFormulaire->setData('date_tdt', $pes['date']);
        $donneesFormulaire->setData('id_retour', $pes['id']);
        $donneesFormulaire->setData('envoi_ged', true);

        $this->objectInstancier->getInstance(DocumentTitre::class)->update($new_id_d);

        $donneesFormulaire->addFileFromData('fichier_pes', $nom_pes, $fic_pes);

        $this->objectInstancier->getInstance(ActionChange::class)->addAction(
            $new_id_d,
            $id_e,
            0,
            Action::CREATION,
            'Importation du PES Retour avec succès'
        );

        $this->objectInstancier->getInstance(NotificationMail::class)->notify(
            $id_e,
            $new_id_d,
            Action::CREATION,
            self::FLUX_PES_RETOUR,
            'Importation du PES Retour avec succès'
        );

        //passage à l'etat lu
        $this->exec(self::URL_HELIOS_PES_RETOUR_UPDATE . '?id=' . $pes['id']);

        return true;
    }

    /**
     * @throws Exception
     * @throws S2lowException
     */
    public function getPESRetourLu(DonneesFormulaire $donneesFormulaire): bool
    {
        // helios_get_retour de PES Retour lu
        $idRetour = $donneesFormulaire->get('id_retour');
        $nomPes = $donneesFormulaire->get('objet') . '.xml';
        $ficPes = $this->exec(self::URL_HELIOS_PES_RETOUR_GET . "?id=$idRetour", false);
        $donneesFormulaire->addFileFromData('fichier_pes', $nomPes, $ficPes);
        return true;
    }

    public function getLastReponseFile(): string
    {
        return $this->reponseFile;
    }

    /**
     * @throws S2lowException
     */
    public function getDateAR($id_transaction): string
    {
        $result = $this->exec(self::URL_STATUS . "?transaction=$id_transaction");
        return substr($result, strpos($result, 'actes:DateReception') + 21, 10);
    }

    /**
     * @throws S2lowException
     */
    public function getBordereau($id_transaction): bool|string
    {
        return $this->exec(self::URL_BORDEREAU . "?trans_id=$id_transaction", false);
    }

    /**
     * @throws JsonException
     * @throws S2lowException
     */
    public function getActeTamponne($id_transaction, ?string $date_affichage = null): ?string
    {
        $file_list = $this->getActeTamponneS2lowFileList($id_transaction);
        return $this->getActeTamponneS2low($file_list, $date_affichage);
    }

    /**
     * @throws JsonException
     */
    private function getActeTamponneS2lowFileList(string $id_transaction): array
    {
        try {
            $url = self::URL_GET_FILE_LIST . "?transaction=$id_transaction";
            $file_list = $this->exec($url);
        } catch (Exception) {
            return [];
        }
        if (!$file_list) {
            return [];
        }
        $file_list = json_decode($file_list, true, 512, JSON_THROW_ON_ERROR);
        if (!$file_list) {
            return [];
        }
        return $file_list;
    }

    /**
     * @throws S2lowException
     */
    private function getActeTamponneS2low(array $file_list, ?string $date_affichage = null): ?string
    {
        if ($file_list[1]['mimetype'] !== 'application/pdf') {
            return null;
        }
        $url = self::URL_DOWNLOAD_FILE . "?file={$file_list[1]['id']}&tampon=true";
        if ($date_affichage) {
            $url .= "&date_affichage=$date_affichage";
        }
        return $this->exec($url, false);
    }

    /**
     * @throws RecoverableException
     * @throws S2lowException
     */
    public function getFichierRetour($transaction_id): bool|string
    {
        $result = $this->exec(self::URL_HELIOS_RETOUR . "?id=$transaction_id");
        $simpleXMLWrapper = new SimpleXMLWrapper();
        try {
            $simpleXMLWrapper->loadString($result);
        } catch (SimpleXMLWrapperException $e) {
            throw new RecoverableException(
                "Impossible d'analyser le fichier PES Acquit : " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        return $result;
    }

    /**
     * @throws NotFoundException
     * @throws S2lowException
     * @throws UnrecoverableException
     */
    public function getListDocumentPrefecture(): ?int
    {
        $data = [];
        $result = $this->exec(self::URL_ACTES_REPONSE_PREFECTURE_LISTE);

        if ($result) {
            foreach (json_decode($result, true) as $key => $value) {
                /** TODO: bypass bug in s2low < 4.1.1, to be removed */
                if (
                    $value['type'] === TdtConnecteur::COURRIER_SIMPLE
                    && $value['last_status_id'] !== TdtConnecteur::STATUS_ACTES_MESSAGE_PREF_RECU_PAS_D_AR
                ) {
                    continue;
                }
                $data[$key] = $value;
            }
            foreach ($data as $reponse) {
                $this->getDocumentPrefecture($reponse);
            }
            return count($data);
        }
        throw new S2lowException('S2low ne retourne pas de Réponse de la préfecture');
    }

    /**
     * @throws NotFoundException
     * @throws S2lowException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function getDocumentPrefecture(array $reponse = []): bool
    {
        // création document flux actes réponse préfecture non lu
        // création sur l'entité de l'acte d'origine si acte_unique_id est trouvé
        // et que le connecteur s2low est associé au flux de l'acte d'origine

        /** @var DocumentTypeFactory $documentTypeFactory */
        $documentTypeFactory = $this->objectInstancier->getInstance(DocumentTypeFactory::class);
        if (! $documentTypeFactory->isTypePresent(self::FLUX_REPONSE_PREFECTURE)) {
            throw new Exception(
                'Le type ' . self::FLUX_REPONSE_PREFECTURE . " n'existe pas sur cette plateforme Pastell"
            );
        }

        $acteDocumentId = $this->objectInstancier->getInstance(DocumentIndexSQL::class)
            ->getByFieldValue('acte_unique_id', $reponse['unique_id']);

        $id_e = $this->getEntiteForReponsePrefecture($acteDocumentId);

        $documentCreationService = $this->objectInstancier->getInstance(DocumentCreationService::class);
        $new_id_d = $documentCreationService->createDocumentWithoutAuthorizationChecking(
            $id_e,
            self::FLUX_REPONSE_PREFECTURE
        );

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)
            ->get($new_id_d);

        $type = $this->getLibelleType($reponse['type']);
        $nature_int = $this->getIntNatureActe(substr($reponse['unique_id'], -2));

        $donneesFormulaire->setData('acte_nature', $nature_int);
        $donneesFormulaire->setData('numero_de_lacte', $reponse['number']);
        $donneesFormulaire->setData('type_reponse', $reponse['type']);
        $donneesFormulaire->setData('related_transaction_id', $reponse['related_transaction_id']);
        $donneesFormulaire->setData('transaction_id', $reponse['id']);
        $donneesFormulaire->setData('last_status_id', $reponse['last_status_id']);

        if ($acteDocumentId) {
            $acteDocument = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($acteDocumentId);
            $acteEntite = $this->objectInstancier->getInstance(DocumentEntite::class)->getEntite($acteDocumentId);
            $url = sprintf('/Document/detail?id_d=%s&id_e=%s', $acteDocumentId, $acteEntite[0]['id_e']);
            $donneesFormulaire->setData(
                'url_acte',
                $this->objectInstancier->getInstance('site_base') . $url
            );

            $linksToDocuments = [];
            if ($acteDocument->get('reponse_prefecture_file')) {
                $file_content = $acteDocument->getFileContent('reponse_prefecture_file');
                $linksToDocuments = json_decode($file_content, true, 512, JSON_THROW_ON_ERROR);
            }
            $linksToDocuments[$reponse['type']] = sprintf('/Document/detail?id_d=%s&id_e=%s', $new_id_d, $id_e);
            $acteDocument->addFileFromData(
                'reponse_prefecture_file',
                'reponse_prefecture.json',
                json_encode($linksToDocuments, JSON_THROW_ON_ERROR)
            );
            $acteDocument->setData('has_reponse_prefecture', true);
        }

        $file_content = $this->getReponsePrefecture($reponse['id']);

        $donneesFormulaire->setData("has_{$type}", true);
        $donneesFormulaire->setData('date', date('Y-m-d H:i:m'));
        $donneesFormulaire->addFileFromData('reponse_prefecture', "{$type}.tar.gz", $file_content);

        $file_path = $donneesFormulaire->getFilePath('reponse_prefecture');

        $tmpFolder = $this->objectInstancier->getInstance(TmpFolder::class);
        $tmp_folder = $tmpFolder->create();

        $temp_file_path = $tmp_folder . '/fichier.tar.gz';
        copy($file_path, $temp_file_path);

        $result_folder = $tmp_folder . '/result/';
        mkdir($result_folder);

        $command = "tar -zxvf $temp_file_path --directory $result_folder 2>&1";
        exec($command, $output, $return_var);

        $file_list = scandir($result_folder);
        $num_file = 0;
        foreach ($file_list as $file_result) {
            $file_result_path = $result_folder . '/' . $file_result;
            if (is_file($file_result_path)) {
                $donneesFormulaire->addFileFromCopy(
                    'reponse_prefecture_unzip',
                    $file_result,
                    $file_result_path,
                    $num_file++
                );
            }
        }
        $tmpFolder->delete($tmp_folder);

        $this->objectInstancier->getInstance(DocumentTitre::class)->update($new_id_d);

        $actionChange = $this->objectInstancier->getInstance(ActionChange::class);
        if ($reponse['type'] === TdtConnecteur::DEFERE_TRIBUNAL_ADMINISTRATIF) {
            $actionChange->addAction(
                $new_id_d,
                $id_e,
                0,
                'termine',
                'Ce type de réponse de la préfecture ne prévoit pas de retour'
            );
        } else {
            $actionChange->addAction(
                $new_id_d,
                $id_e,
                0,
                'attente-reponse-prefecture',
                "Attente d'une réponse"
            );
        }

        $this->exec(self::URL_ACTES_REPONSE_PREFECTURE_MARK_AS_READ . '?transaction_id=' . $reponse['id']);
        return true;
    }

    private function getEntiteForReponsePrefecture($acteDocumentId): int|string
    {
        $connecteur_info = $this->getConnecteurInfo();
        $id_e = $connecteur_info['id_e'];

        if ($acteDocumentId) {
            $acteEntite = $this->objectInstancier->getInstance(DocumentEntite::class)->getEntite($acteDocumentId);
            $id_e_acte = $acteEntite[0]['id_e'];
            $id_ce_acte = $this->objectInstancier->getInstance(FluxEntiteSQL::class)->getConnecteurId(
                $id_e_acte,
                $acteEntite[0]['last_type'],
                $connecteur_info['type']
            );
            if ($connecteur_info['id_ce'] == $id_ce_acte) {
                $id_e = $id_e_acte;
            }
        }
        return $id_e;
    }

    /**
     * @throws S2lowException
     */
    public function getListReponsePrefecture($transaction_id): array
    {
        $result = [];
        $all_reponse = $this->exec(self::URL_ACTES_REPONSE_PREFECTURE . "?id=$transaction_id");
        $all_reponse = trim($all_reponse);
        if (!$all_reponse) {
            return $result;
        }
        foreach (explode("\n", $all_reponse) as $line) {
            [$type, $status, $id] = explode('-', $line);
            $result[] = ['type' => $type,'status' => $status,'id' => $id];
        }
        return $result;
    }

    /**
     * @throws S2lowException
     */
    public function getReponsePrefecture($transaction_id): bool|string
    {
        return $this->exec(self::URL_ACTES_REPONSE_PREFECTURE . "?id=$transaction_id", false);
    }

    /**
     * @throws S2lowException
     * @throws UnrecoverableException
     */
    public function sendResponse(DonneesFormulaire $donneesFormulaire): void
    {
        $this->sendReponseType((int)$donneesFormulaire->get('type_reponse'), $donneesFormulaire);
    }

    private function getLibelleType($id_type)
    {
        $txt_message = [
            TdtConnecteur::COURRIER_SIMPLE => 'courrier_simple',
            TdtConnecteur::DEMANDE_PIECE_COMPLEMENTAIRE => 'demande_piece_complementaire',
            TdtConnecteur::LETTRE_OBSERVATION => 'lettre_observation',
            TdtConnecteur::DEFERE_TRIBUNAL_ADMINISTRATIF => 'defere_tribunal_administratif'
        ];
        return $txt_message[$id_type];
    }

    /**
     * @throws S2lowException
     * @throws UnrecoverableException
     */
    private function sendReponseType(int $id_type, DonneesFormulaire $donneesFormulaire): void
    {
        $libelle = $this->getLibelleType($id_type);

        $nature_reponse = $donneesFormulaire->get('refus_reponse') ? 3 : 4;
        $file_name = $donneesFormulaire->getFileName('reponse');
        $file_path = $donneesFormulaire->getFilePath('reponse');

        $type_actes_element = 'type_acte_' . $libelle;
        $type_pj_element  = 'type_pj_' . $libelle;

        $type_default = $this->getDefaultTypology(
            $donneesFormulaire->get('acte_nature'),
            $this->classificationFilePath
        );

        if ($donneesFormulaire->get($type_actes_element)) {
            $this->curlWrapper->addPostData('type_acte', $donneesFormulaire->get($type_actes_element));
        } else {
            $this->curlWrapper->addPostData('type_acte', $type_default);
        }

        $id = $donneesFormulaire->get('transaction_id');

        $this->curlWrapper->addPostData('id', $id);
        $this->curlWrapper->addPostData('api', 1);
        $this->curlWrapper->addPostData('type_envoie', $nature_reponse);
        $this->curlWrapper->addPostFile('acte_pdf_file', $file_path, $file_name);

        if (
            $id_type === 3
            && $nature_reponse === 4
            && $donneesFormulaire->get('reponse_pj_demande_piece_complementaire')
        ) {
            foreach ($donneesFormulaire->get('reponse_pj_demande_piece_complementaire') as $i => $file_name) {
                $file_path = $donneesFormulaire->getFilePath('reponse_pj_demande_piece_complementaire', $i);
                $this->curlWrapper->addPostFile('acte_attachments[]', $file_path, $file_name) ;

                if ($donneesFormulaire->get($type_pj_element)) {
                    $type_pj = json_decode($donneesFormulaire->get($type_pj_element))[$i];
                    $this->curlWrapper->addPostData('type_pj[]', $type_pj);
                } else {
                    $this->curlWrapper->addPostData('type_pj[]', $type_default);
                }
            }
        }

        $result = $this->exec(self::URL_POST_REPONSE_PREFECTURE);
        if (!str_starts_with($result, 'OK')) {
            throw new S2lowException('Erreur lors de la transmission, S²low a répondu : ' . $result);
        }

        $ligne = explode("\n", $result);
        $id_transaction = trim($ligne[1]);
        $donneesFormulaire->setData('response_transaction_id', $id_transaction);
    }

    public function getRedirectURLForTeletransimission(): string
    {
        return $this->tedetisURL . self::URL_POST_CONFIRM;
    }

    public function getRedirectURLForTeletransimissionMulti(): string
    {
        return $this->tedetisURL . self::URL_POST_CONFIRM_MULTI;
    }

    /**
     * @throws S2lowException
     * @throws JsonException
     */
    //Cette fonction fonctionne sur une branche de S2low 1.5 ou 2.0
    //Elle ne lance pas d'exception (la branche 1.5 ne connait pas cette fonction).
    //Lorsque la version 1.5 de S2low n'existera plus, il conviendra de modifier la fonction
    //pour qu'elle déclenche de véritables erreurs en cas de problème.
    public function getAnnexesTamponnees(string $transaction_id, ?string $date_affichage = null): array
    {
        try {
            $file_list = $this->exec(self::URL_GET_FILE_LIST . "?transaction=$transaction_id");
        } catch (Exception) {
            return [];
        }
        if (!$file_list) {
            return [];
        }
        $file_list = json_decode($file_list, true, 512, JSON_THROW_ON_ERROR);
        if (!$file_list) {
            return [];
        }

        if (count($file_list) <= 2) {
            return [];
        }
        array_shift($file_list);
        array_shift($file_list);
        $result = [];
        foreach ($file_list as $file) {
            $filename = $file['posted_filename'];
            if ($file['mimetype'] !== 'application/pdf') {
                $result[] = [];
                continue;
            }
            $url = self::URL_DOWNLOAD_FILE . "?file={$file['id']}&tampon=true";
            if ($date_affichage) {
                $url .= "&date_affichage=$date_affichage";
            }
            $content = $this->exec($url, false);
            $result[] = [
                'filename' => $filename,
                'content' => $content
            ];
        }
        return $result;
    }

    public function getNounce(): bool|string
    {
        if (! $this->userLogin) {
            return false;
        }
        try {
            $result = $this->exec(self::URL_GET_NOUNCE);
            $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception) {
            return false;
        }
        $result['login'] = $this->userLogin;
        $result['hash'] = hash('sha256', "{$this->userPassword}:{$result['nounce']}");

        return "nounce={$result['nounce']}&login={$result['login']}&hash={$result['hash']}";
    }

    public function getURLTestNounce(): string
    {
        $url_param = $this->getNounce();
        return $this->tedetisURL . self::URL_TEST . "?$url_param";
    }

    public function getFilenameTransformation(string $filename): string
    {
        return preg_replace('#[^a-zA-Z0-9._ ]#', '_', $filename);
    }
}

//class S2lowException extends TdTException {}
