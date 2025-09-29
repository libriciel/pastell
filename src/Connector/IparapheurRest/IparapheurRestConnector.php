<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest;

use BadMethodCallException;
use DOMDocument;
use DOMException;
use DOMXPath;
use DonneesFormulaire;
use Exception;
use Fichier;
use FileToSign;
use JsonException;
use Libriciel\IparapheurV5\Client\Api\DeskApi;
use Libriciel\IparapheurV5\Client\Api\FolderApi;
use Libriciel\IparapheurV5\Client\Api\TenantApi;
use Libriciel\IparapheurV5\Client\Api\TypologyApi;
use Libriciel\IparapheurV5\Client\Api\WorkflowApi;
use Libriciel\IparapheurV5\Client\Configuration;
use Libriciel\IparapheurV5\Client\Model\Action;
use Libriciel\IparapheurV5\Client\Model\SimpleTaskParams;
use Libriciel\IparapheurV5\Client\ApiException;
use Pastell\Action\TestConnectionInterface;
use Pastell\Client\IparapheurV5\IparapheurAuthConfig;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Client\IparapheurV5\Model\Premis;
use Pastell\Client\IparapheurV5\Model\PremisObject;
use Pastell\Client\IparapheurV5\Model\SignificantProperties;
use Psr\Http\Client\ClientExceptionInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SignatureConnecteur;
use SplFileObject;
use stdClass;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use TmpFolder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use ZipArchive;

use function array_slice;
use function in_array;
use function sprintf;

class IparapheurRestConnector extends SignatureConnecteur implements
    IpRestTenantInterface,
    IpRestDeskInterface,
    TestConnectionInterface
{
    private const string URL = 'url';
    private const string USERNAME = 'username';
    private const string PASSWORD = 'password';
    private const string TENANT_ID = 'tenant_id';
    private const string DESK_ID = 'desk_id';
    private const string TYPE_ID = 'iparapheur_type_id';
    public const int IPARAPHEUR_NB_JOUR_MAX_DEFAULT = SignatureConnecteur::PARAPHEUR_NB_JOUR_MAX_DEFAULT;
    private DonneesFormulaire $connecteurConfig;
    private ClientInterface $client;
    private Configuration $configuration;
    private int $iparapheur_nb_jour_max;
    private string $iparapheur_metadata;
    private ?array $sending_metadata = null;
    private bool $iparapheur_multi_doc;

    public function __construct(
        private readonly ApiClientFactory $apiClientFactory,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $this->connecteurConfig = $donneesFormulaire;
        $this->iparapheur_nb_jour_max = (int)$donneesFormulaire->get('iparapheur_nb_jour_max');
        $this->iparapheur_metadata = (string)$donneesFormulaire->get('iparapheur_metadata');
        $this->iparapheur_multi_doc = $donneesFormulaire->get('iparapheur_multi_doc') === true;
        $iparapheurAuthConfig = new IparapheurAuthConfig(
            $donneesFormulaire->get(self::USERNAME) ?: '',
            $donneesFormulaire->get(self::PASSWORD) ?: '',
            rtrim($donneesFormulaire->get(self::URL), '/') ?: '',
        );
        [$httpClient, $config] = $this->apiClientFactory->createAuthenticatedClient($iparapheurAuthConfig);
        $this->client = $httpClient;
        $this->configuration = $config;
    }

    /**
     * @throws IpRestApiException
     */
    public function testConnexion(): string
    {
        $result = $this->getTenantList();
        if (!$result) {
            return "Connexion réussie, mais aucune entité n'est associée à ce compte";
        }
        return 'Liste des entités iparapheur : ' . implode(', ', $result);
    }

    /**
     * @throws IpRestApiException
     */
    public function getTenantList(): array
    {
        $tenants = [];
        $page = 0;

        do {
            try {
                $result = new TenantApi($this->client, $this->configuration)->listTenants($page);
            } catch (ApiException $e) {
                throw new IpRestApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $e->getCode(),
                        $e->getResponseBody(),
                    )
                );
            }

            foreach ($result->getContent() as $tenant) {
                $tenants[$tenant->getId()] = $tenant->getName();
            }

            $pageable = $result->getPageable();
            $currentPage = $pageable ? $pageable->getPageNumber() : $page;
            $totalPages = $result->getTotalPages() ?? 1;

            $page++;
        } while ($currentPage + 1 < $totalPages);

        return $tenants;
    }

    /**
     * @throws IpRestException
     * @throws IpRestApiException
     */
    public function getDeskList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        if (!$tenantId) {
            throw new IpRestException("L'entité iparapheur est obligatoire pour voir la liste des bureaux");
        }

        $desks = [];
        $page = 0;

        do {
            try {
                $result = new DeskApi($this->client, $this->configuration)->listUserDesks($tenantId, $page);
            } catch (ApiException $e) {
                throw new IpRestApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $e->getCode(),
                        $e->getResponseBody(),
                    )
                );
            }

            foreach ($result->getContent() as $desk) {
                $desks[$desk->getId()] = $desk->getName();
            }

            $pageable = $result->getPageable();
            $currentPage = $pageable ? $pageable->getPageNumber() : $page;
            $totalPages = $result->getTotalPages() ?? 1;

            $page++;
        } while ($currentPage + 1 < $totalPages);

        return $desks;
    }

    /**
     * @throws IpRestException
     * @throws IpRestApiException
     */
    public function getTypeList(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        if (!$tenantId) {
            throw new IpRestException("L'entité iparapheur est obligatoire pour voir la liste des types");
        }

        $types = [];
        $page = 0;

        do {
            try {
                $result = new TypologyApi($this->client, $this->configuration)->listTypes($tenantId, $page);
            } catch (ApiException $e) {
                throw new IpRestApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $e->getCode(),
                        $e->getResponseBody(),
                    )
                );
            }

            foreach ($result->getContent() as $type) {
                $types[$type->getId()] = $type->getName();
            }

            $pageable = $result->getPageable();
            $currentPage = $pageable ? $pageable->getPageNumber() : $page;
            $totalPages = $result->getTotalPages() ?? 1;

            $page++;
        } while ($currentPage + 1 < $totalPages);

        return $types;
    }

    /**
     * @throws IpRestException
     * @throws IpRestApiException
     */
    public function getSousType(): array
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        $typeId = $this->connecteurConfig->get(self::TYPE_ID);

        if ((!$tenantId) || (!$typeId)) {
            throw new IpRestException(
                "L'entité et le type iparapheur sont obligatoires pour voir la liste des sous-types"
            );
        }

        $subTypes = [];
        $page = 0;

        do {
            try {
                $result = new TypologyApi($this->client, $this->configuration)->listSubtypes($tenantId, $typeId, $page);
            } catch (ApiException $e) {
                throw new IpRestApiException(
                    sprintf(
                        '[%d] Error connecting to the API (%s)',
                        $e->getCode(),
                        $e->getResponseBody(),
                    )
                );
            }

            foreach ($result->getContent() as $subType) {
                $subTypes[$subType->getId()] = $subType->getName();
            }

            $pageable = $result->getPageable();
            $currentPage = $pageable ? $pageable->getPageNumber() : $page;
            $totalPages = $result->getTotalPages() ?? 1;

            $page++;
        } while ($currentPage + 1 < $totalPages);

        return $subTypes;
    }

    /**
     * @throws IpRestApiException
     * @throws Exception
     */
    public function getPremis(string $folderId): Premis
    {
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID, '');
        $deskId = $this->connecteurConfig->get(self::DESK_ID, '');

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();

        try {
            $premisXml = new FolderApi($this->client, $this->configuration)
                ->downloadFolderPremis($tenantId, $deskId, $folderId);

            $propertyInfo = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
            $normalizer = new ObjectNormalizer(null, null, null, $propertyInfo);

            $serializer = new Serializer(
                [$normalizer, new ArrayDenormalizer()],
                [new XmlEncoder()]
            );

            /** @var Premis $premis */
            $premis = $serializer->deserialize($premisXml, Premis::class, 'xml');
            $dom = new DOMDocument();
            $dom->loadXML($premisXml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $objectNodes = $dom->getElementsByTagName('object');
            foreach ($objectNodes as $index => $node) {
                $type = $node->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'type');
                if (isset($premis->object[$index])) {
                    $premis->object[$index]->type = $type;
                }
            }
            return $premis;
        } catch (ApiException $e) {
            throw new IpRestApiException(
                sprintf(
                    '[%d] Error connecting to the API (%s)',
                    $e->getCode(),
                    $e->getResponseBody(),
                )
            );
        } finally {
            $tmpFolder->delete($tmp_folder);
        }
    }

    public function getNbJourMaxInConnecteur(): int
    {
        if ($this->iparapheur_nb_jour_max) {
            return $this->iparapheur_nb_jour_max;
        }
        return self::IPARAPHEUR_NB_JOUR_MAX_DEFAULT;
    }

    /**
     * @throws DOMException
     * @throws IpRestApiException
     */
    public function sendDossier(FileToSign $dossier): string|false
    {
        if ($this->sending_metadata) {
            $dossier->metadata = $this->sending_metadata;
        }

        $tempFiles = [];
        try {
            $premis = Premis::fromFileToSign($dossier, $this->iparapheur_multi_doc);
            $xml = $premis->generateDraftPremis();

            $premisPath = tempnam(sys_get_temp_dir(), 'folder-premis-') . '.xml';
            file_put_contents($premisPath, $xml);
            $folderFile = new SplFileObject($premisPath, 'r');
            $tempFiles[] = $premisPath;

            $mainPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $dossier->document->filename;
            file_put_contents($mainPath, $dossier->document->content);
            $documents = [new SplFileObject($mainPath, 'r')];
            $tempFiles[] = $mainPath;

            foreach ($dossier->annexes as $annexe) {
                $annexePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $annexe->filename;
                file_put_contents($annexePath, $annexe->content);
                $documents[] = new SplFileObject($annexePath, 'r');
                $tempFiles[] = $annexePath;
            }

            $tenantId = $this->connecteurConfig->get(self::TENANT_ID, '');
            $deskId = $this->connecteurConfig->get(self::DESK_ID, '');
            $result = new FolderApi($this->client, $this->configuration)->createFolder(
                $tenantId,
                $deskId,
                $folderFile,
                $documents,
                false
            );
            $folderId = $result->getId();
            if ($folderId === null) {
                return false;
            }
            $created_premis = $this->getPremis($folderId);
            $start_task_id = $created_premis->getStartEvent()->eventIdentifier->eventIdentifierValue;
            $simple_task_params = $this->createSimpleTaskParamsFromFileToSign($dossier);
            new WorkflowApi($this->client, $this->configuration)->start(
                $tenantId,
                $deskId,
                $folderId,
                $start_task_id,
                $simple_task_params
            );
            return $folderId;
        } catch (ApiException $e) {
            throw new IpRestApiException(
                sprintf(
                    '[%d] Error connecting to the API (%s)',
                    $e->getCode(),
                    $e->getResponseBody(),
                )
            );
        } finally {
            foreach ($tempFiles as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function createSimpleTaskParamsFromFileToSign(FileToSign $fileToSign): SimpleTaskParams
    {
        $taskParams = new SimpleTaskParams();

        $taskParams->setPublicAnnotation($fileToSign->annotationPublic ?? '');
        $taskParams->setPrivateAnnotation($fileToSign->annotationPrivee ?? '');

        return $taskParams;
    }


    /**
     * @throws Exception
     */
    public function getSignature($dossierID, $archive = true): array
    {
        $premis = $this->getPremis($dossierID);
        $tenantId = $this->connecteurConfig->get(self::TENANT_ID);
        $deskId = $this->connecteurConfig->get(self::DESK_ID, '');
        try {
            $zipData = new FolderApi($this->client, $this->configuration)->downloadFolderZip(
                $tenantId,
                $deskId,
                $dossierID
            );
        } catch (ApiException $e) {
            throw new IpRestApiException(
                sprintf(
                    '[%d] Error connecting to the API (%s)',
                    $e->getCode(),
                    $e->getResponseBody(),
                )
            );
        }

        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $tmp_path = $tmp_folder . "/$dossierID.zip";
        file_put_contents($tmp_path, $zipData);

        $zip = new ZipArchive();
        if ($zip->open($tmp_path) === true) {
            $zip->extractTo($tmp_folder);
            $zip->close();
        } else {
            throw new RuntimeException("Impossible d'extraire l'archive ZIP.");
        }

        $info = [];
        $info['bordereau'] = null;
        $info['meta_donnees'] = [];
        $info['documents'] = [];
        $info['detached_signatures'] = [];
        $info['annexes'] = [];
        $info['premis'] = $premis;
        $info['is_pes'] = false;
        $info['is_detached'] = false;

        foreach ($premis->object as $object) {
            if ($object->type === PremisObject::FILE && isset($object->signatureInformation)) {
                $info['is_detached'] = true;
                break;
            }
        }

        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp_folder));
        $filesMap = [];
        foreach ($rii as $file) {
            if ($file->isFile()) {
                $filesMap[$file->getFilename()] = [
                    'content' => file_get_contents($file->getPathname()),
                    'path' => $file->getPathname(),
                ];
            }
        }

        $entity = $premis->getIntellectualEntity();
        $bordereauFilename = $entity->originalName . '_bordereau.pdf';
        if (isset($filesMap[$bordereauFilename])) {
            $fichier = new Fichier();
            $fichier->filename = $bordereauFilename;
            $fichier->content = $filesMap[$bordereauFilename]['content'];
            $info['bordereau'] = $fichier;
            unset($filesMap[$bordereauFilename]);
        }

        foreach ($premis->object as $object) {
            if ($object->type !== PremisObject::FILE) {
                continue;
            }
            $filename = $object->originalName;
            if (!isset($filesMap[$filename])) {
                continue;
            }
            $fichier = new Fichier();
            $fichier->filename = $filename;
            $fichier->content = $filesMap[$filename]['content'];
            foreach ($object->significantProperties as $prop) {
                if ($prop->significantPropertiesType === SignificantProperties::MAIN_DOCUMENT) {
                    $target = strtolower($prop->significantPropertiesValue) === SignificantProperties::TRUE
                        ? 'documents'
                        : 'annexes';
                    $info[$target][] = $fichier;
                    break;
                }
            }
            if (isset($object->signatureInformation)) {
                $info['is_detached'] = true;
            }
            unset($filesMap[$filename]);
        }

        if ($info['is_detached']) {
            foreach ($filesMap as $filename => $data) {
                if (str_contains($data['path'], DIRECTORY_SEPARATOR . 'Documents principaux' . DIRECTORY_SEPARATOR)) {
                    $fichier = new Fichier();
                    $fichier->filename = $filename;
                    $fichier->content = $data['content'];
                    $info['detached_signatures'][] = $fichier;
                }
            }
        }
        if ($archive) {
            $this->archiver($dossierID);
        }

        return $info;
    }

    public function archiver($dossierID): bool
    {
        return $this->deleteFolder($dossierID);
    }

    /**
     * @throws IpRestApiException
     */
    public function getAllHistoriqueInfo($dossierID): stdClass
    {
        $premis = $this->getPremis($dossierID);
        $events = $premis->getAllCurrentEvents();

        $logDossier = [];

        foreach ($events as $event) {
            $timestamp = $event->eventDateTime ?? '';
            $agentName = '';
            if (
                !empty($event->linkingAgentIdentifier) &&
                !empty($event->linkingAgentIdentifier->linkingAgentIdentifierValue)
            ) {
                $agentName = $premis->getAgent($event->linkingAgentIdentifier->linkingAgentIdentifierValue)->agentName;
            }

            $annotation = \sprintf(
                '(bureau %s) %s',
                $event->linkingAgentIdentifier->linkingAgentRole ?? '',
                $event->eventOutcomeInformation->eventOutcomeDetail->eventOutcomeDetailNote ?? '',
            );

            $logDossier[] = (object)[
                'timestamp' => $timestamp,
                'nom' => $agentName,
                'status' => $event->eventType,
                'annotation' => $annotation,
            ];
        }


        $result = new stdClass();
        $result->LogDossier = $logDossier;

        return $result;
    }

    /**
     * @param $history - output of IparapheurRestConnector::getAllHistoriqueInfo()
     * @throws Exception
     */
    public function getLastHistorique($history): string
    {
        $lastLog = end($history->LogDossier);
        return sprintf(
            'Étape en cours : [%s] %s',
            $lastLog->status,
            $lastLog->annotation
        );
    }

    /**
     * @throws IpRestApiException
     */
    public function getRefusalMessage($dossierID): string
    {
        return $this->getPremis($dossierID)->getRefusalMessage();
    }

    /**
     * @param $history - output of IparapheurRestConnector::getAllHistoriqueInfo()
     */
    public function getDateSignature(array|stdClass $history): string
    {
        foreach (array_reverse($history->LogDossier) as $log) {
            if (in_array($log->status, [Action::SIGNATURE, Action::EXTERNAL_SIGNATURE], true)) {
                $logSignature = $log;
                break;
            }
        }
        return isset($logSignature) ? date('Y-m-d', strtotime($logSignature->timestamp)) : '';
    }

    public function effacerDossierRejete($dossierID): bool
    {
        return $this->deleteFolder($dossierID);
    }

    public function exercerDroitRemordDossier($dossierID): bool
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * @param $lastHistorique - output of IparapheurRestConnector::getLastHistorique()
     */
    public function isFinalState(string $lastHistorique): bool
    {
        return str_contains($lastHistorique, '[' . Action::ARCHIVE . ']');
    }

    /**
     * @param $lastHistorique - output of IparapheurRestConnector::getLastHistorique()
     */
    public function isRejected(string $lastHistorique): bool
    {
        return str_contains($lastHistorique, '[' . Action::DELETE . ']');
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function hasMultiDocumentSigne($info): bool
    {
        return $this->iparapheur_multi_doc && count($info['documents']) > 1;
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function isDetached($info): bool
    {
        return $info['is_detached'];
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function getDetachedSignature($info): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'detached-signature-zip-');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Impossible de créer l'archive ZIP");
        }

        /** @var Fichier $file */
        foreach ($info['detached_signatures'] as $file) {
            $zip->addFromString($file->filename, $file->content);
        }

        $zip->close();

        $zipContent = file_get_contents($zipPath);
        unlink($zipPath);

        return $zipContent ?: '';
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function getSignedFile($info)
    {
        /** @var Fichier $signedFile */
        $signedFile = $info['documents'][0];
        return $signedFile->content;
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function getBordereauFromSignature($info, string $documentId = ''): ?Fichier
    {
        /** @var Fichier $bordereau */
        $bordereau = $info['bordereau'];
        return $bordereau;
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     * @throws JsonException
     */
    public function getMetadataSortie($info): ?Fichier
    {
        /** @var Premis $premis */
        $premis = $info['premis'];
        foreach ($premis->object as $object) {
            if ($object->type === PremisObject::INTELLECTUAL_ENTITY) {
                $metadata = [];
                foreach ($object->significantProperties as $property) {
                    $metadata[$property->significantPropertiesType] = $property->significantPropertiesValue;
                }

                $file = new Fichier();
                $file->filename = 'metadonneesSortieParapheur.json';
                $file->content = json_encode($metadata, JSON_THROW_ON_ERROR);
                return $file;
            }
        }
        return null;
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function getAllDocumentSigne(array $info): array
    {
        $documents = [];
        foreach ($info['documents'] as $document) {
            /** @var Fichier $document */
            $documents[] = [
                'nom_document' => $document->filename,
                'document' => $document->content,
            ];
        }
        return $documents;
    }

    /**
     * @param array $info output of IparapheurRestConnector::getSignature()
     */
    public function getOutputAnnexe($info, int $ignore_count): array
    {
        if (empty($info['annexes'])) {
            return [];
        }

        return array_map(static function ($fichier) {
            return [
                'nom_document' => $fichier->filename,
                'document' => $fichier->content,
            ];
        }, array_slice($info['annexes'], $ignore_count));
    }

    public function setSendingMetadata(DonneesFormulaire $donneesFormulaire): void
    {
        $all_metadata = explode(',', $this->iparapheur_metadata);
        $result = [];
        foreach ($all_metadata as $metadata_association) {
            [$element_pastell, $metadata_parapheur] = array_pad(explode(':', $metadata_association, 2), 2, null);
            if ($element_pastell && $metadata_parapheur) {
                $result[$metadata_parapheur] = $donneesFormulaire->get(trim($element_pastell), '');
            }
        }

        $this->sending_metadata = $result;
    }

    /**
     * @throws IpRestApiException
     */
    private function deleteFolder(string $folderId): bool
    {
        try {
            $this->getLogger()->debug("Effacement du dossier $folderId");
            $tenantId = $this->connecteurConfig->get(self::TENANT_ID, '');
            $deskId = $this->connecteurConfig->get(self::DESK_ID, '');
            new FolderApi($this->client, $this->configuration)->deleteFolder(
                $tenantId,
                $deskId,
                $folderId
            );
            $this->getLogger()->debug("Dossier $folderId supprimé");
        } catch (ApiException $e) {
            throw new IpRestApiException(
                sprintf(
                    '[%d] Error connecting to the API (%s)',
                    $e->getCode(),
                    $e->getResponseBody(),
                )
            );
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            $this->getLogger()->notice("Impossible d'effacer le dossier $folderId : " . $e->getMessage());
            return false;
        }
        return true;
    }

    public function getLastCompletedHistorique($history): string
    {
        for ($i = count($history->LogDossier) - 1; $i >= 0; $i--) {
            $log = $history->LogDossier[$i];
            if ($log->timestamp !== '' && $log->status !== Action::READ) {
                return sprintf(
                    '%s : [%s] %s',
                    date('d/m/Y H:i:s', strtotime($log->timestamp)),
                    $log->status,
                    $log->annotation
                );
            }
        }
        return 'Aucune étape complétée';
    }
}
