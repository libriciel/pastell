<?php

declare(strict_types=1);

namespace Pastell\Command\Workspace;

use Exception;
use Pastell\Command\BaseCommand;
use Pastell\Service\Document\DocumentSize;
use Pastell\Service\Workspace\WorkspaceFileOrphan;
use PastellLogger;
use SQLQuery;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkspaceOrphanCheck extends BaseCommand
{
    private const TYPE_DOCUMENT = 'document';
    private const TYPE_CONNECTEUR = 'connecteur';

    private const CONNECTEUR_PREFIX = 'connecteur_';

    private const EXISTS_CHECK_CHUNK = 1000;

    /** @var array<string, array<int, string>> */
    private array $dirEntriesCache = [];

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly PastellLogger $pastellLogger,
        private readonly DocumentSize $documentSize,
        private readonly string $workspacePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:workspace:orphan-check')
            ->setDescription(
                'Vérifie la présence dans le workspace de fichiers de documents ou de connecteurs dont '
                . "l'identifiant ne référence plus aucune ligne en base, et propose de les supprimer"
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Supprime les fichiers orphelins sans demande de confirmation'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run - ne supprime rien')
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                \sprintf(
                    'Restreint la vérification à un type (%s ou %s)',
                    self::TYPE_DOCUMENT,
                    self::TYPE_CONNECTEUR
                )
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $type = $input->getOption('type');

        if ($type !== null && ! \in_array($type, [self::TYPE_DOCUMENT, self::TYPE_CONNECTEUR], true)) {
            $this->getIO()->error(\sprintf('Type inconnu "%s"', $type));
            return self::FAILURE;
        }

        $this->getIO()->title('Vérification des fichiers orphelins du workspace');

        if (! is_dir($this->workspacePath)) {
            $this->getIO()->error(\sprintf('Workspace introuvable : %s', $this->workspacePath));
            return self::FAILURE;
        }

        $orphans = [];
        if ($type === null || $type === self::TYPE_DOCUMENT) {
            $orphans = array_merge($orphans, $this->findDocumentOrphans());
        }
        if ($type === null || $type === self::TYPE_CONNECTEUR) {
            $orphans = array_merge($orphans, $this->findConnecteurOrphans());
        }

        if (! $orphans) {
            $this->getIO()->success('Aucun fichier orphelin trouvé');
            return self::SUCCESS;
        }

        $totalFiles = 0;
        $totalSize = 0;
        $rows = [];
        foreach ($orphans as $orphan) {
            $totalFiles += \count($orphan->files);
            $totalSize += $orphan->size;
            $rows[] = [
                $orphan->type,
                $orphan->id,
                basename($orphan->ymlPath),
                \count($orphan->files),
                $this->documentSize->getHumanReadableSize($orphan->size),
            ];
        }
        $rows[] = new TableSeparator();
        $rows[] = [
            \sprintf('Total : %d élément(s)', \count($orphans)),
            '',
            '',
            $totalFiles,
            $this->documentSize->getHumanReadableSize($totalSize),
        ];

        $this->getIO()->table(
            ['Type', 'Identifiant', 'Fichier principal', 'Nb fichiers', 'Taille'],
            $rows
        );

        if ($dryRun) {
            $this->getIO()->success(\sprintf('%d élément(s) orphelin(s) auraient été supprimé(s)', \count($orphans)));
            return self::SUCCESS;
        }

        $confirm = $force || $this->getIO()->confirm(
            \sprintf('Supprimer ces %d élément(s) orphelin(s) ?', \count($orphans)),
            false
        );
        if (! $confirm) {
            $this->getIO()->note('Abandon de la suppression des fichiers orphelins');
            return self::FAILURE;
        }

        $deleted = 0;
        $failed = 0;
        foreach ($orphans as $orphan) {
            $this->deleteOrphan($orphan) ? $deleted++ : $failed++;
        }

        if ($failed > 0) {
            $this->getIO()->warning(\sprintf(
                '%d élément(s) orphelin(s) supprimé(s), %d en échec (voir les logs)',
                $deleted,
                $failed
            ));
            return self::FAILURE;
        }

        $this->getIO()->success(\sprintf('%d élément(s) orphelin(s) supprimé(s)', $deleted));
        return self::SUCCESS;
    }

    /**
     * Documents: workspace/{a}/{b}/{id_d}.yml
     *
     * @return list<WorkspaceFileOrphan>
     * @throws Exception
     */
    private function findDocumentOrphans(): array
    {
        $ymlById = [];
        foreach ($this->listSubDirs($this->workspacePath) as $levelOne) {
            foreach ($this->listSubDirs($levelOne) as $levelTwo) {
                foreach ($this->listYmlFiles($levelTwo) as $id_d => $ymlPath) {
                    $ymlById[$id_d] = ['dir' => $levelTwo, 'yml_path' => $ymlPath];
                }
            }
        }

        return $this->buildOrphans(
            self::TYPE_DOCUMENT,
            $ymlById,
            $this->filterExisting('document', 'id_d', array_keys($ymlById))
        );
    }

    /**
     * Connectors: workspace/connecteur_{id_ce}.yml
     *
     * @return list<WorkspaceFileOrphan>
     * @throws Exception
     */
    private function findConnecteurOrphans(): array
    {
        $ymlById = [];
        foreach ($this->listYmlFiles($this->workspacePath) as $basename => $ymlPath) {
            if (! str_starts_with($basename, self::CONNECTEUR_PREFIX)) {
                continue;
            }
            $id_ce = substr($basename, \strlen(self::CONNECTEUR_PREFIX));
            $ymlById[$id_ce] = ['dir' => $this->workspacePath, 'yml_path' => $ymlPath];
        }

        return $this->buildOrphans(
            self::TYPE_CONNECTEUR,
            $ymlById,
            $this->filterExisting('connecteur_entite', 'id_ce', array_keys($ymlById))
        );
    }

    /**
     * @return list<WorkspaceFileOrphan>
     */
    private function buildOrphans(string $type, array $ymlById, array $existing): array
    {
        $orphans = [];
        foreach ($ymlById as $id => $entry) {
            if (isset($existing[(string) $id])) {
                continue;
            }
            $files = $this->relatedFiles($entry['dir'], basename($entry['yml_path']));
            $orphans[] = new WorkspaceFileOrphan(
                $type,
                (string) $id,
                $entry['yml_path'],
                $files,
                array_sum(array_map(static fn (string $file) => (int) filesize($file), $files)),
            );
        }
        return $orphans;
    }

    /**
     * @throws Exception
     */
    private function filterExisting(string $table, string $column, array $ids): array
    {
        $existing = [];
        foreach (array_chunk($ids, self::EXISTS_CHECK_CHUNK) as $chunk) {
            $placeholders = implode(', ', array_fill(0, \count($chunk), '?'));
            $sql = \sprintf('SELECT `%s` FROM `%s` WHERE `%s` IN (%s)', $column, $table, $column, $placeholders);
            foreach ($this->sqlQuery->query($sql, $chunk) as $row) {
                $existing[(string) $row[$column]] = true;
            }
        }
        return $existing;
    }

    private function deleteOrphan(WorkspaceFileOrphan $orphan): bool
    {
        $deletedFiles = 0;
        foreach ($orphan->files as $file) {
            if (unlink($file)) {
                $deletedFiles++;
            } else {
                $this->pastellLogger->error(\sprintf('Impossible de supprimer le fichier orphelin %s', $file));
            }
        }
        $this->pastellLogger->info(\sprintf(
            'Suppression de %d/%d fichier(s) orphelin(s) du workspace pour le %s "%s" (aucune référence en base)',
            $deletedFiles,
            \count($orphan->files),
            $orphan->type,
            $orphan->id,
        ));
        return $deletedFiles === \count($orphan->files);
    }

    /**
     * The yml file and its attached files ({basename}_{field}_{num}).
     *
     * @return array<int, string>
     */
    private function relatedFiles(string $dir, string $ymlBasename): array
    {
        $entries = $this->dirEntriesCache[$dir] ??= (scandir($dir) ?: []);
        $files = [];
        foreach ($entries as $entry) {
            if (str_starts_with($entry, $ymlBasename) && is_file($dir . '/' . $entry)) {
                $files[] = $dir . '/' . $entry;
            }
        }
        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function listSubDirs(string $dir): array
    {
        $subDirs = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $subDirs[] = $path;
            }
        }
        return $subDirs;
    }

    /**
     * @return array<string, string> path indexed by name without the .yml extension
     */
    private function listYmlFiles(string $dir): array
    {
        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (str_ends_with($entry, '.yml') && is_file($dir . '/' . $entry)) {
                $files[basename($entry, '.yml')] = $dir . '/' . $entry;
            }
        }
        return $files;
    }
}
