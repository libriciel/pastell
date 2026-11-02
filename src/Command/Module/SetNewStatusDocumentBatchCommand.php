<?php

declare(strict_types=1);

namespace Pastell\Command\Module;

use Pastell\Configuration\DocumentTypeValidation;
use Pastell\Service\Document\DocumentEntiteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:module:set-new-status-document-batch',
    description:
    'Set a new status to a document batch having a specific type and status from one entity (option: sub entities)',
)]
class SetNewStatusDocumentBatchCommand extends Command
{
    public const ID_E = 'id_e';
    public const TYPE = 'type';
    public const OLD_STATUS = 'old_status';
    public const NEW_STATUS = 'new_status';
    private const INCLUDE_SUB_ENTITIES = 'includeSubEntities';

    public function __construct(
        private readonly \JobManager $jobManager,
        private readonly DocumentEntiteService $documentEntiteService,
        private readonly \ActionChange $actionChange,
        private readonly \FluxDefinitionFiles $fluxDefinitionFiles,
        private readonly DocumentTypeValidation $documentTypeValidation,
        private readonly \EntiteSQL $entiteSQL,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ID_E, InputArgument::REQUIRED, 'entity id')
            ->addArgument(self::TYPE, InputArgument::REQUIRED, 'document type')
            ->addArgument(self::OLD_STATUS, InputArgument::REQUIRED, 'old status')
            ->addArgument(self::NEW_STATUS, InputArgument::REQUIRED, 'new status')
            ->addOption(
                self::INCLUDE_SUB_ENTITIES,
                'i',
                InputOption::VALUE_NONE,
                "Sets '-i' to include sub entities"
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id_e = intval($input->getArgument(self::ID_E));
        $type = $input->getArgument(self::TYPE);
        $oldStatus = $input->getArgument(self::OLD_STATUS);
        $newStatus = $input->getArgument(self::NEW_STATUS);
        $includeSubEntities = $input->getOption(self::INCLUDE_SUB_ENTITIES) ?? false;

        $documents = $this->getDocuments($id_e, $type, $oldStatus, $includeSubEntities);

        if (count($documents) === 0) {
            $io->note(sprintf(
                "Il n'existe aucun document de type : %s pour l'entité : %d ayant pour statut : %s.",
                $type,
                $id_e,
                $oldStatus
            ));
            return Command::SUCCESS;
        }

        $this->checkExistingAction($type, $newStatus, $io);

        $message = sprintf(
            '%d documents, type : %s et entité : %d, vont passer du statut %s au statut %s.',
            count($documents),
            $type,
            $id_e,
            $oldStatus,
            $newStatus
        );
        if ($includeSubEntities) {
            $message = sprintf('%s (Sous entitées incluses)', $message);
        }
        $io->note($message);

        $answer = $io->ask('Etes-vous sûr (o/N) ?');
        if ($answer != 'o') {
            $io->note("Aucun document n'a été modifié");
            return Command::SUCCESS;
        }

        $io->progressStart(count($documents));

        foreach ($documents as $document) {
            $io->write(sprintf("Modification de : %s\n", $document['id_d']));
            $this->actionChange->addAction(
                $document['id_d'],
                $document['id_e'],
                0,
                $newStatus,
                'Modification via la commande set-new-status-document-batch'
            );
            $this->jobManager->setJobForDocument(
                $document['id_e'],
                $document['id_d'],
                'Lancement du job via set-new-status-document-batch'
            );
            $io->progressAdvance();
            $io->newLine();
        }

        $io->progressFinish();

        $oldStatusDocuments = $this->getDocuments($id_e, $type, $oldStatus, $includeSubEntities);
        $modifiedDocumentNb = count($documents) - count($oldStatusDocuments);
        $io->success(sprintf("%d documents ont été modifiés\n", $modifiedDocumentNb));

        if (count($oldStatusDocuments) > 0) {
            $warning = "Les documents suivants n'ont pas été modifiés :\n";
            foreach ($oldStatusDocuments as $document) {
                $warning .= $document['id_d'] . "\n";
            }
            $io->warning($warning);
        } else {
            $io->success('Done.');
        }

        return Command::SUCCESS;
    }

    public function getDocuments(int $id_e, string $type, string $oldStatus, bool $includeSubEntities): array
    {
        $documents = $this->documentEntiteService->getDocumentsByLastAction($id_e, $type, $oldStatus);
        if ($includeSubEntities) {
            $subEntities = $this->entiteSQL->getAllChildren($id_e);
            foreach ($subEntities as $entity) {
                $documents = array_merge(
                    $documents,
                    $this->documentEntiteService->getDocumentsByLastAction((int) $entity['id_e'], $type, $oldStatus)
                );
            }
        }
        return $documents;
    }

    public function checkExistingAction(string $type, string $newStatus, SymfonyStyle $io): void
    {
        $path = $this->fluxDefinitionFiles->getDefinitionPath($type);
        $file = Yaml::parseFile($path);
        $actions = $this->documentTypeValidation->getAllPossibleAction($file);

        if (!in_array($newStatus, $actions)) {
            $io->warning(sprintf("Le statut %s n'existe pas.", $newStatus));
        }
    }
}
