<?php

namespace Pastell\Command\Module;

use ConnecteurFactory;
use DocumentSQL;
use DonneesFormulaireFactory;
use EntiteSQL;
use Exception;
use FakeGED;
use GEDConnecteur;
use Journal;
use NotFoundException;
use Pastell\Command\BaseCommand;
use Pastell\Service\Document\DocumentDeletionService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ForceSendGedAndDelete extends BaseCommand
{
    private const CONNECTOR_TYPE = 'GED';

    public function __construct(
        private readonly DocumentSQL $documentSQL,
        private readonly EntiteSQL $entiteSQL,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly Journal $journal,
        private readonly DocumentDeletionService $documentDeletionService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:module:force-send-ged-and-delete')
            ->setDescription(
                'Force send-ged and delete documents for source module by id_e (and sub entities optional)'
            )
            ->addArgument('sourceModule', InputOption::VALUE_REQUIRED, 'The source module')
            ->addArgument('entityId', InputOption::VALUE_REQUIRED, 'The entityId id_e')
            ->addOption(
                'includeSubEntities',
                'i',
                InputOption::VALUE_NONE,
                "Sets '-i' to include sub entities"
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run - will not do anything');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sourceModule = $input->getArgument('sourceModule');
        $entityId = (int)$input->getArgument('entityId');
        $includeSubEntities = $input->getOption('includeSubEntities') ?? false;
        $dryRun = $input->getOption('dry-run');

        $messageEntity = sprintf(
            '%s %s',
            'id_e=' . $entityId,
            ($includeSubEntities) ? 'and sub entities' : ''
        );
        $this->getIO()->title(
            sprintf(
                'Start Force send-ged and delete documents `%s` for %s',
                $sourceModule,
                $messageEntity
            )
        );

        $entitiesDocuments = $this->getEntitiesDocuments($sourceModule, $entityId, $includeSubEntities);
        $documentsNumber = count($entitiesDocuments, COUNT_RECURSIVE) - count($entitiesDocuments);

        if ($documentsNumber === 0) {
            $this->getIO()->success(
                sprintf(
                    'There is no document `%s` for %s',
                    $sourceModule,
                    $messageEntity
                )
            );
            return 0;
        }
        if ($dryRun) {
            $this->getIO()->note('Dry run');
        } elseif (
            $input->isInteractive() &&
            !$this->getIO()->confirm(
                sprintf(
                    'Are you sure you want to send-ged end delete %d `%s` for %s ?',
                    $documentsNumber,
                    $sourceModule,
                    $messageEntity
                ),
                false
            )
        ) {
            return 1;
        }
        $this->getIO()->progressStart($documentsNumber);
        $this->getIO()->newLine();
        $errorNumber = 0;
        $messageSuccess = "";

        foreach ($entitiesDocuments as $entity => $documents) {
            $documentsNumberEntity = count($documents);
            $this->getIO()->newLine();
            $this->getIO()->writeln(
                sprintf(
                    '%d documents %s for id_e=%d:',
                    $documentsNumberEntity,
                    $sourceModule,
                    $entity
                )
            );
            try {
                $connector = $this->getDepotConnecteur($sourceModule, $entity);
            } catch (Exception $e) {
                $this->getIO()->error($e->getMessage());
                $errorNumber += $documentsNumberEntity;
                $messageSuccess .= sprintf(
                    ' - id_e=%d (0/%d) [ERROR] %s' . PHP_EOL,
                    $entity,
                    $documentsNumberEntity,
                    $e->getMessage(),
                );
                continue;
            }

            $this->getIO()->writeln(
                sprintf(
                    'Connector %s %s for id_e=%d',
                    self::CONNECTOR_TYPE,
                    $connector::class,
                    $entity,
                )
            );
            $successNumberForEntity = $this->forceSendAndDelete($connector, $documents, $dryRun);
            $messageSuccess .= sprintf(
                ' - id_e=%d (%d/%d) Send %s and delete' . PHP_EOL,
                $entity,
                $successNumberForEntity,
                $documentsNumberEntity,
                $connector::class
            );
            $errorNumber += $documentsNumberEntity - $successNumberForEntity;
        }

        $messageSuccess = sprintf(
            '`app:module:force-send-ged-and-delete` %d documents `%s` for %s. ' . PHP_EOL .
            'Success for %d and failure for %d : ' . PHP_EOL . '%s',
            $documentsNumber,
            $sourceModule,
            $messageEntity,
            ($documentsNumber - $errorNumber),
            $errorNumber,
            $messageSuccess
        );

        if (!$dryRun) {
            $this->journal->addSQL(
                Journal::COMMANDE,
                $entityId,
                0,
                '',
                '',
                $messageSuccess
            );
        }

        $this->getIO()->progressFinish();
        $this->getIO()->success($messageSuccess);
        return 0;
    }


    private function getEntitiesDocuments(string $sourceModule, int $entityId, bool $includeSubEntities): array
    {
        $entitiesDocuments = [];

        if ($includeSubEntities) {
            $subEntities = $this->entiteSQL->getAllChildren($entityId);
            foreach ($subEntities as $entity) {
                $entities[] = $entity['id_e'];
            }
        }
        $entities[] = $entityId;

        $allDocuments = $this->documentSQL->getAllIdByType($sourceModule);
        foreach ($allDocuments as $document) {
            if (in_array($document['id_e'], $entities)) {
                $entitiesDocuments[$document['id_e']][] = $document['id_d'];
            }
        }

        return $entitiesDocuments;
    }

    /**
     * @throws Exception
     */
    private function getDepotConnecteur(string $sourceModule, int $entity): GEDConnecteur
    {
        /** @var GEDConnecteur|false $connector */
        $connector = $this->connecteurFactory->getConnecteurByType($entity, $sourceModule, self::CONNECTOR_TYPE);

        if (!$connector) {
            throw new Exception(sprintf(
                'Connector %s not found for `%s` id_e=%d',
                self::CONNECTOR_TYPE,
                $sourceModule,
                $entity,
            ));
        }

        if ($connector instanceof FakeGED) {
            throw new Exception(sprintf(
                'Connector %s invalid %s for `%s` id_e=%d',
                self::CONNECTOR_TYPE,
                $connector::class,
                $sourceModule,
                $entity,
            ));
        }
        return $connector;
    }

    /**
     * @param GEDConnecteur $connector
     * @throws NotFoundException
     */
    private function forceSendAndDelete(
        GEDConnecteur $connector,
        array $documents,
        bool $dryRun
    ): int {

        $successNumberForEntity = 0;

        foreach ($documents as $documentId) {
            $document = $this->donneesFormulaireFactory->get($documentId);
            if (!$dryRun) {
                try {
                    $connector->send($document);
                    $this->documentDeletionService->delete($documentId);
                    $successNumberForEntity++;
                } catch (Exception $e) {
                    $this->getIO()->writeln(
                        sprintf(
                            '[ERROR] for %s - %s',
                            $documentId,
                            $e->getMessage()
                        )
                    );
                    continue;
                }
            }
            $this->getIO()->progressAdvance();
        }

        return $successNumberForEntity;
    }
}
