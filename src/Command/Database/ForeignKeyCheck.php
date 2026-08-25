<?php

declare(strict_types=1);

namespace Pastell\Command\Database;

use EntiteSQL;
use Exception;
use LogicException;
use Pastell\Command\BaseCommand;
use PastellLogger;
use RoleUtilisateur;
use SQLQuery;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ForeignKeyCheck extends BaseCommand
{
    private const RELATIONS = [
        'annuaire' => [
            'id_e' => ['entite', 'id_e'],
        ],
        'annuaire_groupe' => [
            'id_e' => ['entite', 'id_e'],
        ],
        'annuaire_groupe_contact' => [
            'id_g' => ['annuaire_groupe', 'id_g'],
            'id_a' => ['annuaire', 'id_a'],
        ],
        'annuaire_role' => [
            'id_e_owner' => ['entite', 'id_e'],
            'id_e' => ['entite', 'id_e'],
            'role' => ['role', 'role'],
        ],
        'collectivite_fournisseur' => [
            'id_e_col' => ['entite', 'id_e'],
            'id_e_fournisseur' => ['entite', 'id_e'],
        ],
        'connecteur_action' => [
            'id_e' => ['entite', 'id_e'],
            'id_u' => ['utilisateur', 'id_u'],
            'id_ce' => ['connecteur_entite', 'id_ce'],
        ],
        'connecteur_entite' => [
            'id_e' => ['entite', 'id_e'],
        ],
        'connecteur_frequence' => [
            'id_ce' => ['connecteur_entite', 'id_ce'],
        ],
        'document_action' => [
            'id_d' => ['document', 'id_d'],
            'id_e' => ['entite', 'id_e'],
            'id_u' => ['utilisateur', 'id_u'],
        ],
        'document_action_entite' => [
            'id_a' => ['document_action', 'id_a'],
            'id_e' => ['entite', 'id_e'],
            'id_j' => ['journal', 'id_j'],
        ],
        'document_email' => [
            'id_d' => ['document', 'id_d'],
        ],
        'document_email_reponse' => [
            'id_de' => ['document_email', 'id_de'],
            'id_d_reponse' => ['document', 'id_d'],
        ],
        'document_entite' => [
            'id_d' => ['document', 'id_d'],
            'id_e' => ['entite', 'id_e'],
        ],
        'document_index' => [
            'id_d' => ['document', 'id_d'],
        ],
        'entite' => [
            'entite_mere' => ['entite', 'id_e'],
        ],
        'entite_ancetre' => [
            'id_e' => ['entite', 'id_e'],
            'id_e_ancetre' => ['entite', 'id_e'],
        ],
        'entite_properties' => [
            'id_e' => ['entite', 'id_e'],
        ],
        'flux_entite' => [
            'id_e' => ['entite', 'id_e'],
            'id_ce' => ['connecteur_entite', 'id_ce'],
        ],
        'flux_entite_heritage' => [
            'id_e' => ['entite', 'id_e'],
        ],
        'job_queue' => [
            'id_e' => ['entite', 'id_e'],
            'id_d' => ['document', 'id_d'],
            'id_u' => ['utilisateur', 'id_u'],
            'id_ce' => ['connecteur_entite', 'id_ce'],
        ],
        'journal' => [
            'id_e' => ['entite', 'id_e'],
            'id_u' => ['utilisateur', 'id_u'],
            'id_d' => ['document', 'id_d'],
        ],
        'journal_attente_preuve' => [
            'id_j' => ['journal', 'id_j'],
        ],
        'journal_historique' => [
            'id_e' => ['entite', 'id_e'],
            'id_u' => ['utilisateur', 'id_u'],
            'id_d' => ['document', 'id_d'],
        ],
        'notification' => [
            'id_u' => ['utilisateur', 'id_u'],
            'id_e' => ['entite', 'id_e'],
        ],
        'notification_digest' => [
            'id_e' => ['entite', 'id_e'],
            'id_d' => ['document', 'id_d'],
        ],
        'role_droit' => [
            'role' => ['role', 'role'],
        ],
        'type_dossier_action' => [
            'id_u' => ['utilisateur', 'id_u'],
            'id_t' => ['type_dossier', 'id_t'],
        ],
        'users_token' => [
            'id_u' => ['utilisateur', 'id_u'],
        ],
        'utilisateur' => [
            'id_e' => ['entite', 'id_e'],
        ],
        'utilisateur_new_email' => [
            'id_u' => ['utilisateur', 'id_u'],
        ],
        'utilisateur_role' => [
            'id_u' => ['utilisateur', 'id_u'],
            'id_e' => ['entite', 'id_e'],
            'role' => ['role', 'role'],
        ],
        'worker' => [
            'id_job' => ['job_queue', 'id_job'],
        ],
    ];

    private const EMPTY_VALUES = ['', '0', 0, null];

    private const MAX_CASCADE_ITERATIONS = 20;

    private const ACTION_REATTACH = 'reattach';
    private const ACTION_PROTECT = 'protect';
    private const ACTION_DELETE = 'delete';

    private const IGNORED_VALUES = [
        'utilisateur_role' => [
            'role' => [RoleUtilisateur::AUCUN_DROIT],
        ],
    ];

    private const REATTACH_RELATIONS = [
        'utilisateur' => ['id_e' => EntiteSQL::ID_E_ENTITE_RACINE],
        'entite' => ['entite_mere' => EntiteSQL::ID_E_ENTITE_RACINE],
    ];

    private const PROTECTED_TABLES = [
        'document_action',
        'document_action_entite',
        'journal',
        'journal_historique',
        'journal_attente_preuve',
    ];

    public function __construct(
        private readonly SQLQuery $sqlQuery,
        private readonly EntiteSQL $entiteSQL,
        private readonly PastellLogger $pastellLogger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:database:foreign-key-check')
            ->setDescription(
                "Vérifie l'absence de données dont la clé étrangère ne référence plus aucune ligne existante, "
                . 'et propose de les supprimer'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Supprime les lignes orphelines sans demande de confirmation'
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run - ne supprime rien')
            ->addOption(
                'cascade',
                'c',
                InputOption::VALUE_NONE,
                'Relance automatiquement la vérification après chaque suppression, tant que de nouvelles '
                . 'lignes orphelines supprimables apparaissent (limité à ' . self::MAX_CASCADE_ITERATIONS
                . ' passes, sans effet en --dry-run)'
            )
            ->setHelp(
                'Supprimer une ligne orpheline peut à son tour rendre orphelines des lignes qui en '
                . 'dépendaient (ex : supprimer un connecteur_entite orphelin peut créer des orphelins dans '
                . 'connecteur_frequence, flux_entite ou job_queue). Une seule passe ne converge donc pas '
                . 'toujours : relancez la commande jusqu\'à obtenir "Aucune ligne orpheline trouvée", ou '
                . 'utilisez --cascade pour automatiser ces relances.'
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $cascade = $input->getOption('cascade') && ! $dryRun;

        $this->getIO()->title('Vérification des clés étrangères');

        $totalProcessedRows = 0;
        $iteration = 1;
        while (true) {
            if ($iteration > 1) {
                $this->getIO()->section(\sprintf('Passe n°%d', $iteration));
            }

            [$status, $processedRows] = $this->runOnePass($force, $dryRun);
            $totalProcessedRows += $processedRows;

            if ($status !== self::SUCCESS || ! $cascade || $processedRows === 0) {
                if ($cascade && $iteration > 1) {
                    $this->getIO()->note(\sprintf(
                        '%d ligne(s) orpheline(s) traitée(s) au total en %d passe(s)',
                        $totalProcessedRows,
                        $iteration
                    ));
                } elseif (! $cascade && $processedRows > 0) {
                    $this->getIO()->note(
                        'Ce traitement a pu créer de nouvelles lignes orphelines en cascade : '
                        . 'relancez la commande (ou utilisez --cascade) jusqu\'à obtenir '
                        . '"Aucune ligne orpheline trouvée".'
                    );
                }
                return $status;
            }

            if ($iteration >= self::MAX_CASCADE_ITERATIONS) {
                $this->getIO()->warning(\sprintf(
                    'Limite de %d passes atteinte, arrêt du mode cascade. Relancez la commande pour continuer.',
                    self::MAX_CASCADE_ITERATIONS
                ));
                return self::SUCCESS;
            }

            $iteration++;
        }
    }

    /**
     * @return array{0: int, 1: int} [code retour, nombre de lignes traitées]
     * @throws Exception
     */
    private function runOnePass(bool $force, bool $dryRun): array
    {
        $orphans = $this->findOrphans();

        if (! $orphans) {
            $this->getIO()->success('Aucune ligne orpheline trouvée');
            return [self::SUCCESS, 0];
        }

        $reattachable = $this->filterByAction($orphans, self::ACTION_REATTACH);
        $deletable = $this->filterByAction($orphans, self::ACTION_DELETE);
        $protected = $this->filterByAction($orphans, self::ACTION_PROTECT);

        $this->getIO()->table(
            ['Table', 'Colonne', 'Table référencée', 'Colonne référencée', 'Valeur manquante', 'Nb lignes', 'Action'],
            array_map(
                fn (array $orphan) => [
                    $orphan['table'],
                    $orphan['column'],
                    $orphan['parent_table'],
                    $orphan['parent_column'],
                    $orphan['value'],
                    $orphan['count'],
                    $this->actionLabel($orphan['action']),
                ],
                $orphans
            )
        );

        $this->getIO()->note(\sprintf(
            '%d ligne(s) orpheline(s) trouvée(s) sur %d relation(s)',
            $this->countRows($orphans),
            \count($orphans)
        ));

        if ($protected) {
            $this->getIO()->note(\sprintf(
                '%d ligne(s) sur %d relation(s) protégée(s) (traçabilité) : '
                . 'signalées uniquement, jamais modifiées automatiquement, même avec --force.',
                $this->countRows($protected),
                \count($protected)
            ));
        }

        if (! $reattachable && ! $deletable) {
            $this->getIO()->success('Aucune ligne orpheline à traiter automatiquement');
            return [self::SUCCESS, 0];
        }

        $deletableRows = $this->countRows($deletable);
        $reattachableRows = $this->countRows($reattachable);
        $processedRows = $deletableRows + $reattachableRows;

        if ($dryRun) {
            $this->getIO()->note('Dry run');
        } else {
            $confirm = $force || $this->getIO()->confirm(
                \sprintf(
                    'Traiter ces %d ligne(s) orpheline(s) (%d supprimée(s), %d rattachée(s) à l\'entité racine) ?',
                    $processedRows,
                    $deletableRows,
                    $reattachableRows
                ),
                false
            );
            if (! $confirm) {
                $this->getIO()->note('Abandon du traitement des lignes orphelines');
                return [self::FAILURE, 0];
            }
        }

        if ($dryRun) {
            $this->getIO()->success(\sprintf('%d ligne(s) orpheline(s) auraient été traitée(s)', $processedRows));
            return [self::SUCCESS, 0];
        }

        foreach ($deletable as $orphan) {
            $this->deleteOrphan($orphan);
        }
        foreach ($reattachable as $orphan) {
            $this->reattachOrphan($orphan);
        }
        $this->rebuildCachesAfterReattach($reattachable);

        $this->getIO()->success(\sprintf(
            '%d ligne(s) orpheline(s) traitée(s) (%d supprimée(s), %d rattachée(s))',
            $processedRows,
            $deletableRows,
            $reattachableRows
        ));
        return [self::SUCCESS, $processedRows];
    }

    /**
     * @param array<int, array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int, action: string}> $orphans
     * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int, action: string}>
     */
    private function filterByAction(array $orphans, string $action): array
    {
        return array_values(array_filter($orphans, static fn (array $orphan) => $orphan['action'] === $action));
    }

    /**
     * @param array<int, array{table: string, column: string, value: string}> $orphans
     * @throws Exception
     */
    private function countRows(array $orphans): int
    {
        $byTable = [];
        foreach ($orphans as $orphan) {
            $byTable[$orphan['table']][$orphan['column']][] = $orphan['value'];
        }

        $total = 0;
        foreach ($byTable as $table => $valuesByColumn) {
            $total += $this->countDistinctRows($table, $valuesByColumn);
        }
        return $total;
    }

    /**
     * @param array<string, array<int, string>> $valuesByColumn
     * @throws Exception
     */
    private function countDistinctRows(string $table, array $valuesByColumn): int
    {
        $conditions = [];
        $params = [];
        foreach ($valuesByColumn as $column => $values) {
            $placeholders = implode(', ', array_fill(0, \count($values), '?'));
            $conditions[] = \sprintf('`%s` IN (%s)', $column, $placeholders);
            $params = array_merge($params, $values);
        }

        $sql = \sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $table, implode(' OR ', $conditions));
        return (int) $this->sqlQuery->queryOne($sql, $params);
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_REATTACH => 'rattachement entité racine',
            self::ACTION_PROTECT => 'signalement seul',
            default => 'supprimable',
        };
    }

    /**
     * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int, action: string}>
     * @throws Exception
     */
    private function findOrphans(): array
    {
        $orphans = [];
        foreach (self::RELATIONS as $table => $columns) {
            foreach ($columns as $column => [$parentTable, $parentColumn]) {
                foreach ($this->findOrphansForRelation($table, $column, $parentTable, $parentColumn) as $orphan) {
                    $orphans[] = $orphan;
                }
            }
        }
        return $orphans;
    }

    private function resolveAction(string $table, string $column): string
    {
        if (isset(self::REATTACH_RELATIONS[$table][$column])) {
            return self::ACTION_REATTACH;
        }
        if (\in_array($table, self::PROTECTED_TABLES, true)) {
            return self::ACTION_PROTECT;
        }
        return self::ACTION_DELETE;
    }

    /**
     * @param array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int, action: string} $orphan
     * @throws Exception
     */
    private function deleteOrphan(array $orphan): void
    {
        if ($this->resolveAction($orphan['table'], $orphan['column']) !== self::ACTION_DELETE) {
            throw new LogicException(\sprintf(
                'Refus de supprimer une ligne orpheline sur la relation non supprimable %s.%s',
                $orphan['table'],
                $orphan['column']
            ));
        }

        $sql = \sprintf(
            'DELETE FROM `%s` WHERE `%s` = ?',
            $orphan['table'],
            $orphan['column']
        );
        $this->sqlQuery->query($sql, [$orphan['value']]);
        $this->pastellLogger->info(\sprintf(
            'Suppression de %d ligne(s) orpheline(s) de `%s` (`%s` = "%s", référence manquante vers `%s`.`%s`)',
            $orphan['count'],
            $orphan['table'],
            $orphan['column'],
            $orphan['value'],
            $orphan['parent_table'],
            $orphan['parent_column'],
        ));
    }

    /**
     * @param array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int, action: string} $orphan
     * @throws Exception
     */
    private function reattachOrphan(array $orphan): void
    {
        $target = self::REATTACH_RELATIONS[$orphan['table']][$orphan['column']] ?? null;
        if ($target === null) {
            throw new LogicException(\sprintf(
                'Aucune cible de rattachement définie pour %s.%s',
                $orphan['table'],
                $orphan['column']
            ));
        }

        $sql = \sprintf(
            'UPDATE `%s` SET `%s` = ? WHERE `%s` = ?',
            $orphan['table'],
            $orphan['column'],
            $orphan['column']
        );
        $this->sqlQuery->query($sql, [$target, $orphan['value']]);
        $this->pastellLogger->info(\sprintf(
            'Rattachement de %d ligne(s) de `%s` à l\'entité racine (`%s` : "%s" -> %s)',
            $orphan['count'],
            $orphan['table'],
            $orphan['column'],
            $orphan['value'],
            $target,
        ));
    }

    /**
     * @param array<int, array{table: string, column: string}> $reattached
     */
    private function rebuildCachesAfterReattach(array $reattached): void
    {
        foreach ($reattached as $orphan) {
            if ($orphan['table'] === 'entite' && $orphan['column'] === 'entite_mere') {
                $this->entiteSQL->updateAllAncestors();
                return;
            }
        }
    }

    /**
     * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int, action: string}>
     * @throws Exception
     */
    private function findOrphansForRelation(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn
    ): array {
        $sql = \sprintf(
            'SELECT c.`%s` AS fk_value, COUNT(*) AS nb
             FROM `%s` c
             LEFT JOIN `%s` p ON c.`%s` = p.`%s`
             WHERE p.`%s` IS NULL
             GROUP BY c.`%s`',
            $column,
            $table,
            $parentTable,
            $column,
            $parentColumn,
            $parentColumn,
            $column
        );

        $ignoredValues = self::IGNORED_VALUES[$table][$column] ?? [];

        $orphans = [];
        foreach ($this->sqlQuery->query($sql) as $row) {
            if (\in_array($row['fk_value'], self::EMPTY_VALUES, true)) {
                continue;
            }
            if (\in_array($row['fk_value'], $ignoredValues, true)) {
                continue;
            }
            $orphans[] = [
                'table' => $table,
                'column' => $column,
                'parent_table' => $parentTable,
                'parent_column' => $parentColumn,
                'value' => (string) $row['fk_value'],
                'count' => (int) $row['nb'],
                'action' => $this->resolveAction($table, $column),
            ];
        }
        return $orphans;
    }
}
