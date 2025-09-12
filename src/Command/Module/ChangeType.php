<?php

declare(strict_types=1);

namespace Pastell\Command\Module;

use DocumentSQL;
use SQLQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:module:change-type',
    description: 'Change le type (flux) de tous les documents, met à jour les droits (role_droit)' .
    "et les associations (flux_entite) du module : remplace l'ancien type par le nouveau."
)]
final class ChangeType extends Command
{
    private const string OLD_TYPE = 'ancien_type';
    private const string NEW_TYPE = 'nouveau_type';

    public function __construct(
        private readonly DocumentSQL $documentSQL,
        private readonly SQLQuery $sqlQuery
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::OLD_TYPE, InputArgument::REQUIRED, 'Ancien type (flux) de document')
            ->addArgument(self::NEW_TYPE, InputArgument::REQUIRED, 'Nouveau type (flux) de document');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $status = Command::SUCCESS;

        $oldType = (string)$input->getArgument(self::OLD_TYPE);
        $newType = (string)$input->getArgument(self::NEW_TYPE);

        $documents = $this->documentSQL->getAllByType($oldType);

        if ($documents) {
            $io->writeln('Les documents suivants vont être modifiés (type remplacé) :');
            foreach ($documents as $line) {
                $io->writeln(\sprintf('%s : %s', $line['id_d'], $line['titre']));
            }
            $io->newLine();
            $io->note(\sprintf('%d documents vont être modifiés.', count($documents)));

            $answer = $io->ask('Êtes-vous sûr (o/N) ?');
            if ($answer !== 'o') {
                $io->note("Aucune modification n'a été effectuée");
            } else {
                $sql = <<<SQL
UPDATE document SET type= ? WHERE type = ?
SQL;
                $this->sqlQuery->query($sql, [$newType, $oldType]);

                $sql = <<<SQL
UPDATE role_droit
SET droit = CONCAT(?, SUBSTRING(droit, CHAR_LENGTH(?) + 1))
WHERE droit LIKE CONCAT(?, ":%")
SQL;
                $this->sqlQuery->query($sql, [$newType, $oldType, $oldType]);

                $sql = <<<SQL
UPDATE flux_entite SET flux= ? WHERE flux = ?
SQL;
                $this->sqlQuery->query($sql, [$newType, $oldType]);
                $io->success('Le type (flux) des documents a été mis à jour.');
            }
        } else {
            $io->warning("Il n'y a pas de document de type {$oldType}");
            $status = Command::INVALID;
        }

        return $status;
    }
}
