<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupActesS2low;

use DonneesFormulaire;
use Pastell\Actes\ActeEnvelopeParser;
use Pastell\Client\S2low\Model\ActeListQuery;
use Pastell\Client\S2low\Model\ActeListResponse;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientAuth;
use Pastell\Client\S2low\S2lowClientException;
use Pastell\Client\S2low\S2lowClientFactory;
use Pastell\Service\Document\DocumentDeletionService;
use Psr\Http\Client\ClientExceptionInterface;
use Recuperateur;

class RecupActesS2lowConnector extends \Connecteur
{
    private const FLUX = 'ls-recup-actes-s2low';
    private const STATUS_ACK = 4;
    private const STATUS_SENT_TO_SAE = '12';
    private S2lowClient $client;
    private string $startDate;
    private string $endDate;
    private int $transactionStatus;
    private int $numberOfDocumentsPerJob;
    private int $maxNumberOfDocumentsInEntity;

    public function __construct(
        private readonly S2lowClientFactory $s2lowClientFactory,
        private readonly \DocumentCreationService $documentCreationService,
        private readonly \DocumentModificationService $documentModificationService,
        private readonly DocumentDeletionService $documentDeletionService,
        private readonly \DonneesFormulaireFactory $formFactory,
        private readonly \JobManager $jobManager,
        private readonly \DocumentEntite $documentEntite,
    ) {
    }

    /**
     * @throws \DateInvalidOperationException
     * @throws \DateMalformedStringException
     * @throws \UnrecoverableException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $url = $donneesFormulaire->get('url');
        $this->transactionStatus = (int)$donneesFormulaire->get('transaction_status') ?: self::STATUS_ACK;
        $this->startDate = $donneesFormulaire->get('start_date');
        $this->endDate = $donneesFormulaire->get('end_date');

        $dateSixtyDaysAgo = new \DateTime();
        $dateSixtyDaysAgo->sub(new \DateInterval('P62D'));
        $connectorDate = new \DateTime($donneesFormulaire->get('end_date'));

        if ($connectorDate > $dateSixtyDaysAgo  && $this->transactionStatus === self::STATUS_ACK) {
            $this->endDate = $dateSixtyDaysAgo->format('Y-m-d');
        } else {
            $this->endDate = $connectorDate->format('Y-m-d');
        }

        $this->numberOfDocumentsPerJob = (int)$donneesFormulaire->get('nb_recup') ?: 10;
        $this->maxNumberOfDocumentsInEntity = (int)$donneesFormulaire->get('nb_documents') ?: 100;

        $auth = new S2lowClientAuth();
        $auth->username = $donneesFormulaire->get('user_login') ?: '';
        $auth->password = $donneesFormulaire->get('user_password') ?: '';

        $auth->user_certificat_password = $donneesFormulaire->get('certificate_password');
        $auth->user_key_pem = $donneesFormulaire->getFilePath('certificate_key');
        $auth->user_certificat_pem = $donneesFormulaire->getFilePath('certificate_pem');
        $this->client = $this->s2lowClientFactory->getClient($url, $auth);
    }

    public function getNumberOfDocumentsToCreate(int $entityId): int
    {
        $maxCreatableDocuments = max(
            $this->maxNumberOfDocumentsInEntity - $this->documentEntite->getNbAll(
                $entityId,
                self::FLUX
            ),
            0
        );

        return min(
            $maxCreatableDocuments,
            $this->numberOfDocumentsPerJob
        );
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getEndDate(): string
    {
        return $this->endDate;
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function testAuth(): string
    {
        return $this->client->connexion()->testConnexion();
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function listActes(int $numberOfTransactions, int $offset = 0): ActeListResponse
    {
        $query = new ActeListQuery();
        $query->limit = $numberOfTransactions;
        $query->minDate = $this->startDate;
        $query->maxDate = $this->endDate;
        $query->offset = $offset;
        $query->statusId = $this->transactionStatus;

        return $this->client->actes()->getActesList($query);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function changeStatus(string $transactionId, string $statusId): void
    {
        $this->client->actes()->changeActeStatus($transactionId, $statusId);
    }

    /**
     * @throws \UnrecoverableException
     * @throws ClientExceptionInterface
     * @throws \NotFoundException
     * @throws S2lowClientException
     * @throws \JsonException
     */
    public function fetchActes(): array
    {
        $entityId = $this->getConnecteurInfo()['id_e'];
        $createdDocuments = 0;
        $offset = 0;
        $numberOfDocumentsToCreate = $this->getNumberOfDocumentsToCreate($entityId);
        $message = [];
        while ($createdDocuments < $numberOfDocumentsToCreate) {
            $listActes = $this->listActes($numberOfDocumentsToCreate, $offset);
            if (count($listActes->transactions) === 0) {
                break;
            }

            foreach ($listActes->transactions as $transaction) {
                if (!$transaction->isActes()) {
                    continue;
                }
                $documentId = $this->documentCreationService->createDocumentWithoutAuthorizationChecking(
                    $entityId,
                    self::FLUX
                );
                try {
                    $this->createDocument($entityId, $documentId, $transaction->id);
                    ++$createdDocuments;

                    $message[] = \sprintf(
                        'Création du document lié à la transaction %s %s : %s',
                        $transaction->id,
                        $transaction->number,
                        $documentId,
                    );
                    if ($createdDocuments >= $numberOfDocumentsToCreate) {
                        break;
                    }
                } catch (\Throwable $e) {
                    $message[] = \sprintf(
                        'Impossible de créer le document lié à la transaction %s %s => %s',
                        $transaction->id,
                        $transaction->number,
                        $e->getMessage(),
                    );
                    $this->documentDeletionService->delete($documentId);
                }
            }
            $offset += $numberOfDocumentsToCreate;
        }

        return $message;
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws \NotFoundException
     * @throws \Exception
     */
    private function createDocument(int $entityId, string $documentId, string $transactionId): void
    {
        $transactionFiles = $this->client->actes()->getFileList($transactionId);
        $numberOfFiles = \count($transactionFiles);
        $xml = $this->client->actes()->downloadFile($transactionFiles[0]->id);
        $envelopeParser = new ActeEnvelopeParser($xml);

        $form = $this->formFactory->get($documentId);

        $form->addFileFromData(
            'arrete',
            $transactionFiles[1]->postedFilename,
            $this->client->actes()->downloadFile($transactionFiles[1]->id)
        );
        if ($numberOfFiles > 2) {
            for ($i = 2; $i < $numberOfFiles; ++$i) {
                $form->addFileFromData(
                    'autre_document_attache',
                    $transactionFiles[$i]->postedFilename,
                    $this->client->actes()->downloadFile($transactionFiles[$i]->id),
                    $i - 2
                );
            }
        }

        $aractes = $this->client->actes()->getAractes($transactionId);
        $form->addFileFromData(
            'aractes',
            'aractes.xml',
            $aractes
        );
        $aractesParser = new ActeEnvelopeParser($aractes);

        $recuperateur = new Recuperateur([
            'transaction_id' => $transactionId,
            'acte_nature' => $envelopeParser->getCodeNatureActe(),
            'numero_de_lacte' => $envelopeParser->getNumeroInterne(),
            'objet' => $envelopeParser->getObjet(),
            'date_de_lacte' => $envelopeParser->getDate(),
            'document_papier' => $envelopeParser->hasDocumentPapier(),
            'classification' => $envelopeParser->getClassification(),
            'date_ar' => $aractesParser->getDateAr(),
            'update_status_actes_s2low_status_1' => self::STATUS_SENT_TO_SAE,
        ]);
        $this->documentModificationService->modifyDocumentWithoutAuthorizationChecking(
            $entityId,
            0,
            $documentId,
            $recuperateur,
            new \FileUploader(),
            true
        );

        $form = $this->formFactory->get($documentId);
        if ($form->isValidable()) {
            $this->jobManager->setTraitementLot($entityId, $documentId, 0, 'orientation');
        }
    }
}
