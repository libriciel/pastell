<?php

declare(strict_types=1);

namespace Pastell\Connector\RecupPesS2low;

use DonneesFormulaire;
use Pastell\Client\S2low\Model\PesAllerListQuery;
use Pastell\Client\S2low\Model\PesAllerListResponse;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientAuth;
use Pastell\Client\S2low\S2lowClientException;
use Pastell\Client\S2low\S2lowClientFactory;
use Pastell\Service\Document\DocumentDeletionService;
use Psr\Http\Client\ClientExceptionInterface;
use Recuperateur;
use DocumentIndexSQL;

class RecupPesS2lowConnector extends \Connecteur
{
    private const FLUX = 'ls-recup-pes-s2low';
    private const STATUS_AVAILABLE = 8;
    private const STATUS_SENT_TO_SAE = '19';
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
        private readonly DocumentIndexSQL $documentIndexSQL,
    ) {
    }

    /**
     * @throws \UnrecoverableException
     */
    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $url = $donneesFormulaire->get('url');
        $this->transactionStatus = (int)$donneesFormulaire->get('transaction_status') ?: self::STATUS_AVAILABLE;
        $this->startDate = $donneesFormulaire->get('start_date');
        $this->endDate = $donneesFormulaire->get('end_date');

        $dateFifteenDaysAgo = new \DateTime();
        $dateFifteenDaysAgo->sub(new \DateInterval('P15D'));
        $connectorDate = new \DateTime($donneesFormulaire->get('end_date'));

        if ($connectorDate > $dateFifteenDaysAgo && $this->transactionStatus === self::STATUS_AVAILABLE) {
            $this->endDate = $dateFifteenDaysAgo->format('Y-m-d');
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
     */
    public function listPes(int $numberOfTransactions, int $offset = 0): PesAllerListResponse
    {
        $query = new PesAllerListQuery();
        $query->limit = $numberOfTransactions;
        $query->minDate = $this->startDate;
        $query->maxDate = $this->endDate;
        $query->offset = $offset;
        $query->statusId = $this->transactionStatus;

        return $this->client->pes()->getPesAllerList($query);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function changeStatus(string $transactionId, string $statusId): void
    {
        $this->client->pes()->changePesStatus($transactionId, $statusId);
    }

    /**
     * @throws \UnrecoverableException
     * @throws ClientExceptionInterface
     * @throws \NotFoundException
     * @throws S2lowClientException
     */
    public function fetchPesAller(): array
    {
        $entityId = $this->getConnecteurInfo()['id_e'];
        $createdDocuments = 0;
        $offset = 0;
        $numberOfDocumentsToCreate = $this->getNumberOfDocumentsToCreate($entityId);
        $message = [];
        while ($createdDocuments < $numberOfDocumentsToCreate) {
            $listPes = $this->listPes($numberOfDocumentsToCreate, $offset);
            if (count($listPes->transactions) === 0) {
                break;
            }

            $createdInThisPage = 0;
            foreach ($listPes->transactions as $transaction) {
                if ($createdDocuments >= $numberOfDocumentsToCreate) {
                    break;
                }
                $transactionId = $transaction['id'];
                if ($this->documentIndexSQL->getByFieldValue('transaction_id', $transactionId)) {
                    $message[] = \sprintf(
                        'Transaction %s déjà importée, ignorée',
                        $transactionId,
                    );
                    continue;
                }
                $documentId = $this->documentCreationService->createDocumentWithoutAuthorizationChecking(
                    $entityId,
                    self::FLUX
                );
                try {
                    $this->createDocument($entityId, $documentId, $transactionId);
                    ++$createdDocuments;
                    ++$createdInThisPage;
                    $message[] = \sprintf(
                        'Création du document lié à la transaction %s : %s',
                        $transactionId,
                        $documentId,
                    );
                } catch (\Throwable $e) {
                    $message[] = \sprintf(
                        'Impossible de créer le document lié à la transaction %s => %s',
                        $transactionId,
                        $e->getMessage(),
                    );
                    $this->documentDeletionService->delete($documentId);
                }
            }

            if ($createdInThisPage === 0) {
                break;
            }
            $offset += $this->numberOfDocumentsPerJob;
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
        $form = $this->formFactory->get($documentId);

        $pesContent = $this->client->pes()->getPes($transactionId);
        $form->addFileFromData(
            'fichier_pes',
            'pes_aller.xml',
            $pesContent
        );
        $form->addFileFromData(
            'pes_acquit',
            'pes_acquit.xml',
            $this->client->pes()->getPesAcquit($transactionId)
        );

        $pesAllerFile = new \PESAllerFile();
        $recuperateur = new Recuperateur([
            'transaction_id' => $transactionId,
            'objet' => $pesAllerFile->getAllInfo($pesContent, false)[\PESAllerFile::NOM_FIC],
            'update_status_pes_s2low_status_1' => self::STATUS_SENT_TO_SAE,
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
