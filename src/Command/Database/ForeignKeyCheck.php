<?php

declare(strict_types=1);

namespace Pastell\Command\Database;

use Exception;
use Pastell\Command\BaseCommand;
use SQLQuery;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ForeignKeyCheck extends BaseCommand
{
    /**
     * Table enfant => [colonne enfant => [table parente, colonne parente]]
     */
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

    public function __construct(
        private readonly SQLQuery $sqlQuery,
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run - ne supprime rien');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force') ?? false;
        $dryRun = $input->getOption('dry-run');

        $this->getIO()->title('Vérification des clés étrangères');
        $orphans = $this->findOrphans();

        if (! $orphans) {
            $this->getIO()->success('Aucune ligne orpheline trouvée');
            return self::SUCCESS;
        }

        $this->getIO()->table(
            ['Table', 'Colonne', 'Table référencée', 'Colonne référencée', 'Valeur manquante', 'Nb lignes'],
            array_map(
                static fn (array $orphan) => [
                    $orphan['table'],
                    $orphan['column'],
                    $orphan['parent_table'],
                    $orphan['parent_column'],
                    $orphan['value'],
                    $orphan['count'],
                ],
                $orphans
            )
        );

        $totalRows = array_sum(array_column($orphans, 'count'));
        $this->getIO()->note(
            \sprintf('%d ligne(s) orpheline(s) trouvée(s) sur %d relation(s)', $totalRows, count($orphans))
        );

        if ($dryRun) {
            $this->getIO()->note('Dry run');
        } else {
            $confirm = $force || $this->getIO()->confirm(
                \sprintf('Êtes-vous certain de vouloir supprimer ces %d ligne(s) ?', $totalRows),
                false
            );
            if (! $confirm) {
                $this->getIO()->note('Abandon de la suppression des lignes orphelines');
                return self::FAILURE;
            }
        }

        foreach ($orphans as $orphan) {
            if (! $dryRun) {
                $this->deleteOrphan($orphan);
            }
        }

        if ($dryRun) {
            $this->getIO()->success(\sprintf('%d ligne(s) orpheline(s) auraient été supprimée(s)', $totalRows));
        } else {
            $this->getIO()->success(\sprintf('%d ligne(s) orpheline(s) supprimée(s)', $totalRows));
        }
        return self::SUCCESS;
    }

    /**
     * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int}>
     */
    private function findOrphans(): array
    {
        $orphans = [];
        foreach (self::RELATIONS as $table => $columns) {
            foreach ($columns as $column => [$parentTable, $parentColumn]) {
                $orphans = array_merge(
                    $orphans,
                    $this->findOrphansForRelation($table, $column, $parentTable, $parentColumn)
                );
            }
        }
        return $orphans;
    }

    /**
     * @param array{table: string, column: string, value: string} $orphan
     * @throws Exception
     */
    private function deleteOrphan(array $orphan): void
    {
        $sql = \sprintf(
            'DELETE FROM `%s` WHERE `%s` = ?',
            $orphan['table'],
            $orphan['column']
        );
        $this->sqlQuery->query($sql, [$orphan['value']]);
    }

    /**
     * @return array<int, array{table: string, column: string, parent_table: string, parent_column: string, value: string, count: int}>
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

        $orphans = [];
        foreach ($this->sqlQuery->query($sql) as $row) {
            if (in_array($row['fk_value'], self::EMPTY_VALUES, true)) {
                continue;
            }
            $orphans[] = [
                'table' => $table,
                'column' => $column,
                'parent_table' => $parentTable,
                'parent_column' => $parentColumn,
                'value' => (string) $row['fk_value'],
                'count' => (int) $row['nb'],
            ];
        }
        return $orphans;
    }
}
