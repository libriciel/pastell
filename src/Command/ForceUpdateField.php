<?php

namespace Pastell\Command;

use Pastell\Service\UpdateFieldService;
use Exception;
use InvalidArgumentException;
use JournalEventType;
use Pastell\Service\Journal\JournalEntryService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function implode;
use function in_array;

class ForceUpdateField extends BaseCommand
{
    private const SCOPE_LIST = [UpdateFieldService::SCOPE_MODULE, UpdateFieldService::SCOPE_CONNECTOR];

    public function __construct(
        private UpdateFieldService $updateFieldService,
        private JournalEntryService $journal,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:force-update-field')
            ->setDescription('Update field by twig expression for all connectors entity or module by type')
            ->addArgument(
                'scope',
                InputArgument::REQUIRED,
                sprintf("Sets the scope of connectors or module (%s)", implode(', ', self::SCOPE_LIST))
            )
            ->addArgument('type', InputArgument::REQUIRED, 'Type of connectors entity or module to update')
            ->addArgument('field', InputArgument::REQUIRED, 'Field to update')
            ->addArgument('twigExpression', InputArgument::REQUIRED, 'Twig expression for update field value')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run - will not update anything');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scope = $input->getArgument('scope');
        $type = $input->getArgument('type');
        $field = $input->getArgument('field');
        $twigExpression = $input->getArgument('twigExpression');
        $dryRun = $input->getOption('dry-run');

        if (!in_array($scope, self::SCOPE_LIST, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Scope `%s` is invalid. It needs to be in (%s)',
                    $scope,
                    implode(', ', self::SCOPE_LIST)
                )
            );
        }

        $this->getIO()->title(
            sprintf(
                'Start Update field `%s` by twig expression `%s` for all %s `%s`',
                $field,
                $twigExpression,
                $scope,
                $type
            )
        );

        $scopeType = ($scope === UpdateFieldService::SCOPE_MODULE) ? 'dossiers' : 'configuration';
        $documents = $this->updateFieldService->getAllDocuments($scope, $type);
        $documentsNumber = count($documents);
        if ($documentsNumber === 0) {
            $this->getIO()->success(
                sprintf('There is no %s %s `%s` to update', $scopeType, $scope, $type)
            );
            return 0;
        }
        if ($dryRun) {
            $this->getIO()->note('Dry run');
        } elseif (
            $input->isInteractive() &&
            !$this->getIO()->confirm(
                sprintf('Are you sure you want to update %d %s %s `%s` ?', $documentsNumber, $scopeType, $scope, $type),
                false
            )
        ) {
            return 1;
        }
        $this->getIO()->progressStart($documentsNumber);
        $this->getIO()->newLine();

        foreach ($documents as $document) {
            $message = $this->updateFieldService->updateField($document, $scope, $field, $twigExpression, $dryRun);
            $this->getIO()->writeln($message);
            $this->getIO()->progressAdvance();
        }

        if (!$dryRun) {
            $this->journal->addSQL(
                JournalEventType::COMMANDE,
                0,
                0,
                '',
                '',
                sprintf(
                    '`app:force-update-field` Update field `%s` by twig expression `%s` for %s %s %s `%s`',
                    $field,
                    $twigExpression,
                    $documentsNumber,
                    $scopeType,
                    $scope,
                    $type
                )
            );
        }

        $this->getIO()->progressFinish();
        $this->getIO()->success('Done');

        return 0;
    }
}
