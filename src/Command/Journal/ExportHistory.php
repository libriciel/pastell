<?php

declare(strict_types=1);

namespace Pastell\Command\Journal;

use CSVoutput;
use DateTime;
use InvalidArgumentException;
use RuntimeException;
use SQLQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:journal:export-history',
    description: 'Exporte journal_historique en CSV sur une période [date_debut; date_fin] (format JJ/MM/AAAA).',
)]
final class ExportHistory extends Command
{
    private const string ARG_DATE_DEBUT = 'date_debut';
    private const string ARG_DATE_FIN = 'date_fin';
    private const string ARG_OUTPUT = 'output_path';

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly CSVoutput $csvOutput,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ARG_DATE_DEBUT, InputArgument::REQUIRED, 'Date début (JJ/MM/AAAA)')
            ->addArgument(self::ARG_DATE_FIN, InputArgument::REQUIRED, 'Date fin (JJ/MM/AAAA)')
            ->addArgument(self::ARG_OUTPUT, InputArgument::REQUIRED, 'Chemin du fichier CSV de sortie');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $exit = Command::SUCCESS;
        $fp = null;
        $buffering = false;
        $outPath = (string)$input->getArgument(self::ARG_OUTPUT);

        try {
            $dateDebutFr = (string)$input->getArgument(self::ARG_DATE_DEBUT);
            $dateFinFr = (string)$input->getArgument(self::ARG_DATE_FIN);

            $dateDebutIso = $this->frToIsoDate($dateDebutFr);
            $dateFinIso = $this->frToIsoDate($dateFinFr);
            if ($dateDebutIso === null || $dateFinIso === null) {
                throw new InvalidArgumentException(
                    'Format attendu : JJ/MM/AAAA. Exemple : 01/06/2015 30/06/2015',
                    Command::INVALID
                );
            }

            $dir = \dirname($outPath);
            if (!is_dir($dir)) {
                throw new RuntimeException(\sprintf('Répertoire de sortie introuvable : %s', $dir));
            }
            if (!is_writable($dir)) {
                throw new RuntimeException(\sprintf('Répertoire non inscriptible : %s', $dir));
            }

            [$sql, $params] = $this->buildQuery($dateDebutIso, $dateFinIso);

            $fp = @fopen($outPath, 'wb');
            if ($fp === false) {
                throw new RuntimeException(\sprintf('Impossible d’ouvrir le fichier de sortie : %s', $outPath));
            }

            $this->sqlQuery->prepareAndExecute($sql, $params);
            ob_start(static function (string $buffer) use ($fp): string {
                fwrite($fp, $buffer);
                return '';
            });
            $buffering = true;

            $this->csvOutput->begin();
            while ($this->sqlQuery->hasMoreResult()) {
                $row = $this->sqlQuery->fetch();
                if (\is_array($row)) {
                    unset($row['preuve']);
                    $this->csvOutput->displayLine($row);
                }
            }
            $this->csvOutput->end();
        } catch (\Throwable $e) {
            $exit = ($e instanceof InvalidArgumentException && $e->getCode() === Command::INVALID)
                ? Command::INVALID
                : Command::FAILURE;

            $io->error('Erreur lors de l’export : ' . $e->getMessage());
        } finally {
            if ($buffering) {
                if ($exit === Command::SUCCESS) {
                    ob_end_flush();
                } else {
                    ob_end_clean();
                }
            }
            if (\is_resource($fp)) {
                fclose($fp);
            }

            if ($exit !== Command::SUCCESS && $outPath !== '' && @is_file($outPath)) {
                @unlink($outPath);
            }
            if ($exit === Command::SUCCESS) {
                $io->success(\sprintf('Export écrit : %s', $outPath));
            }
        }

        return $exit;
    }

    private function frToIsoDate(string $fr): ?string
    {
        $dt = DateTime::createFromFormat('d/m/Y', $fr);
        if (!$dt) {
            return null;
        }
        $dt->setTime(0, 0, 0);
        return $dt->format('Y-m-d');
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function buildQuery(string $dateDebutIso, string $dateFinIso): array
    {
        $params = [];
        $sql =
            'SELECT jh.*, d.titre, e.denomination, u.nom, u.prenom, e.siren
             FROM journal_historique jh
             LEFT JOIN document   d ON jh.id_d = d.id_d
             LEFT JOIN entite     e ON jh.id_e = e.id_e
             LEFT JOIN utilisateur u ON jh.id_u = u.id_u
             WHERE 1=1 ';

        $sql .= 'AND DATE(jh.date) >= ? ';
        $params[] = $dateDebutIso;

        $sql .= 'AND DATE(jh.date) <= ? ';
        $params[] = $dateFinIso;

        $sql .= 'ORDER BY jh.id_j DESC';

        return [$sql, $params];
    }
}
