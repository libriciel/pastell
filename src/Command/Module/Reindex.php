<?php

declare(strict_types=1);

namespace Pastell\Command\Module;

use DocumentControler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:module:reindex',
    description: "Réindexe un ensemble de documents d'un type donné pour un champ donné (offset/limit optionnels).",
)]
final class Reindex extends Command
{
    private const string ARG_TYPE = 'type_de_document';
    private const string ARG_FIELD = 'champ_a_reindexer';
    private const string ARG_OFFSET = 'offset';
    private const string ARG_LIMIT = 'limit';

    public function __construct(
        private readonly DocumentControler $documentControler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_TYPE, InputArgument::REQUIRED, 'Type (flux) des documents')
            ->addArgument(self::ARG_FIELD, InputArgument::REQUIRED, 'Champ à réindexer')
            ->addArgument(self::ARG_OFFSET, InputArgument::OPTIONAL, 'Décalage de départ', '0')
            ->addArgument(self::ARG_LIMIT, InputArgument::OPTIONAL, 'Nombre max de documents (-1 pour illimité)', '-1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $documentType = (string)$input->getArgument(self::ARG_TYPE);
        $field = (string)$input->getArgument(self::ARG_FIELD);
        $offset = (int)$input->getArgument(self::ARG_OFFSET);
        $limit = (int)$input->getArgument(self::ARG_LIMIT);

        try {
            $this->documentControler->reindex($documentType, $field, $offset, $limit);
            $io->success(
                \sprintf(
                    "Réindexation lancée. Type='%s', Champ='%s', Offset=%d, Limit=%d",
                    $documentType,
                    $field,
                    $offset,
                    $limit
                )
            );
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Erreur lors de la réindexation : ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
