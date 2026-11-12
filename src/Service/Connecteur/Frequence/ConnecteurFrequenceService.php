<?php

declare(strict_types=1);

namespace Pastell\Service\Connecteur\Frequence;

use ConnecteurEntiteSQL;
use ConnecteurFrequenceSQL;
use Cron\CronExpression;
use Date;
use DocumentSQL;
use DocumentTypeFactory;
use FluxEntiteSQL;
use InvalidArgumentException;
use Job;
use UnrecoverableException;
use Pastell\Service\Entite\RootEntity;

final class ConnecteurFrequenceService
{
    public const DEFAULT_EXPRESSION = '1';
    public const DEFAULT_ID_VERROU = 'DEFAULT_VERROU_ID';

    private const CONNECTEUR_COLUMN_LIST = ['type_connecteur', 'famille_connecteur', 'id_connecteur', 'id_ce'];
    private const ACTION_COLUMN_LIST = ['action_type', 'type_document', 'action'];

    public function __construct(
        private readonly ConnecteurFrequenceSQL $connecteurFrequenceSQL,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly DocumentSQL $documentSQL,
        private readonly DocumentTypeFactory $documentTypeFactory,
    ) {
    }

    public function get(int $id_cf): ?ConnecteurFrequenceData
    {
        $connecteur_frequence_info = $this->connecteurFrequenceSQL->getInfo($id_cf);
        return $connecteur_frequence_info ? ConnecteurFrequenceData::fromArray($connecteur_frequence_info) : null;
    }

    /**
     * @return ConnecteurFrequenceData[]
     */
    public function getAll(): array
    {
        return \array_map(
            ConnecteurFrequenceData::fromArray(...),
            $this->connecteurFrequenceSQL->getAllArray()
        );
    }

    public function save(ConnecteurFrequenceData $connecteurFrequenceData): int
    {
        $has_famille = $connecteurFrequenceData->type_connecteur !== ''
            && $connecteurFrequenceData->famille_connecteur !== '';
        $id_connecteur = $has_famille ? $connecteurFrequenceData->id_connecteur : '';
        $action_type = $has_famille ? $connecteurFrequenceData->action_type : '';
        $columns = [
            'type_connecteur' => $connecteurFrequenceData->type_connecteur,
            'famille_connecteur' => $has_famille ? $connecteurFrequenceData->famille_connecteur : '',
            'id_connecteur' => $id_connecteur,
            'id_ce' => $id_connecteur !== '' ? ($connecteurFrequenceData->id_ce ?? 0) : 0,
            'action_type' => $action_type,
            'type_document' => $action_type !== '' && $action_type !== ConnecteurFrequenceActionType::CONNECTEUR->value ?
                $connecteurFrequenceData->type_document : '',
            'action' => $action_type !== '' ? $connecteurFrequenceData->action : '',
            'expression' => $connecteurFrequenceData->expression,
            'id_verrou' => $connecteurFrequenceData->id_verrou,
        ];
        if ($connecteurFrequenceData->id_cf === null) {
            return $this->connecteurFrequenceSQL->create(...$columns);
        }
        $this->connecteurFrequenceSQL->update($connecteurFrequenceData->id_cf, ...$columns);
        return $connecteurFrequenceData->id_cf;
    }

    public function delete(int $id_cf): void
    {
        $this->connecteurFrequenceSQL->delete($id_cf);
    }

    public function findNearest(ConnecteurFrequenceData $connecteurFrequenceData): ?ConnecteurFrequenceData
    {
        $candidate_list = $this->connecteurFrequenceSQL->getCandidateList(
            $connecteurFrequenceData->type_connecteur,
            $connecteurFrequenceData->famille_connecteur,
            $connecteurFrequenceData->id_connecteur,
            $connecteurFrequenceData->id_ce ?? 0,
            $connecteurFrequenceData->action_type,
            $connecteurFrequenceData->type_document,
            $connecteurFrequenceData->action,
        );

        $nb_connecteur_column = \count(self::CONNECTEUR_COLUMN_LIST);
        $nb_connecteur_kept = null;
        for ($nb_kept = $nb_connecteur_column; $nb_kept >= 0 && $nb_connecteur_kept === null; $nb_kept--) {
            foreach ($candidate_list as $connecteur_frequence_info) {
                if ($this->isMatching($connecteur_frequence_info, self::CONNECTEUR_COLUMN_LIST, $nb_kept)) {
                    $nb_connecteur_kept = $nb_kept;
                    break;
                }
            }
        }
        if ($nb_connecteur_kept === null) {
            return null;
        }

        $column_list = [...self::CONNECTEUR_COLUMN_LIST, ...self::ACTION_COLUMN_LIST];
        for ($nb_kept = \count($column_list); $nb_kept >= 0; $nb_kept--) {
            foreach ($candidate_list as $connecteur_frequence_info) {
                if ($this->isMatching($connecteur_frequence_info, $column_list, $nb_kept, $nb_connecteur_kept)) {
                    return ConnecteurFrequenceData::fromArray($connecteur_frequence_info);
                }
            }
        }
        return null;
    }

    public function getForConnecteur(array $connecteur_info): ConnecteurFrequenceData
    {
        return $this->findNearestOrDefault(
            $this->getCriteriaFromConnecteur($connecteur_info, ConnecteurFrequenceActionType::CONNECTEUR)
        );
    }

    /**
     * @return array<string, ConnecteurFrequenceData>
     */
    public function getForConnecteurByFlux(array $connecteur_info): array
    {
        if ($connecteur_info['global']) {
            return [];
        }
        $connecteurFrequenceDataByFlux = [];
        foreach ($this->fluxEntiteSQL->getFluxByConnecteur((int)$connecteur_info['id_ce']) as $flux) {
            $connecteurFrequenceDataByFlux[$flux] = $this->findNearestOrDefault(
                $this->getCriteriaFromConnecteur($connecteur_info, ConnecteurFrequenceActionType::DOCUMENT, $flux)
            );
        }
        return $connecteurFrequenceDataByFlux;
    }

    public function getForJob(Job $job): ConnecteurFrequenceData
    {
        $action_type = $job->type === Job::TYPE_CONNECTEUR ?
            ConnecteurFrequenceActionType::CONNECTEUR : ConnecteurFrequenceActionType::DOCUMENT;
        $type_document = $job->id_d ? (string)($this->documentSQL->getInfo($job->id_d)['type'] ?? '') : '';
        $action = (string)$job->etat_cible;
        $connecteur_info = $this->getJobConnecteurInfo($job, $type_document);

        $criteria = $connecteur_info ?
            $this->getCriteriaFromConnecteur($connecteur_info, $action_type, $type_document, $action) :
            new ConnecteurFrequenceData(
                action_type: $action_type->value,
                type_document: $type_document,
                action: $action
            );
        return $this->findNearestOrDefault($criteria);
    }

    /**
     * @throws UnrecoverableException
     * @throws InvalidArgumentException
     */
    public function getNextTry(string $expression, int $nb_try, string $relative_date = ''): string
    {
        if ($expression === '' || $expression === '0') {
            return '';
        }

        $line_list = $this->parseExpression($expression);
        $total_try = 0;
        $i = 0;
        while ($total_try <= $nb_try && isset($line_list[$i]) && $line_list[$i]->nb_try) {
            $total_try += $line_list[$i]->nb_try;
            if ($total_try <= $nb_try) {
                $i++;
            }
        }
        if (!isset($line_list[$i])) {
            throw new UnrecoverableException("Trop d'essai sur le connecteur");
        }

        $line = $line_list[$i];
        if ($line->cron !== '') {
            return (new CronExpression($line->cron))
                ->getNextRunDate($relative_date ?: 'now')
                ->format(Date::DATE_ISO);
        }

        return \date(
            Date::DATE_ISO,
            \strtotime(\sprintf(
                '%s +%d %s',
                $relative_date,
                $line->frequence,
                $line->in_seconds ? 'seconds' : 'minutes'
            ))
        );
    }

    public function getConnecteurLabel(ConnecteurFrequenceData $connecteurFrequenceData): string
    {
        $connecteurFrequenceType = ConnecteurFrequenceType::fromValue($connecteurFrequenceData->type_connecteur);
        if ($connecteurFrequenceType === null) {
            return 'Tous les connecteurs';
        }
        $label = "({$connecteurFrequenceType->label()})";
        if ($connecteurFrequenceData->famille_connecteur === '') {
            return "$label Tous les connecteurs";
        }
        $label .= " $connecteurFrequenceData->famille_connecteur";
        if ($connecteurFrequenceData->id_connecteur === '') {
            return $label;
        }
        return "$label:$connecteurFrequenceData->id_connecteur";
    }

    public function getActionLabel(ConnecteurFrequenceData $connecteurFrequenceData): string
    {
        $connecteurFrequenceActionType = ConnecteurFrequenceActionType::fromValue($connecteurFrequenceData->action_type);
        if ($connecteurFrequenceActionType === null) {
            return 'Toutes les actions';
        }
        $label = "({$connecteurFrequenceActionType->label()}) ";
        if ($connecteurFrequenceActionType === ConnecteurFrequenceActionType::DOCUMENT) {
            if ($connecteurFrequenceData->type_document === '') {
                return $label . 'Tous les types de dossiers';
            }
            $label .= "$connecteurFrequenceData->type_document: ";
        }
        return $label . ($connecteurFrequenceData->action ?: 'toutes les actions');
    }

    public function getInstanceLabel(ConnecteurFrequenceData $connecteurFrequenceData): string
    {
        if ($connecteurFrequenceData->id_ce === null) {
            return '';
        }
        $denomination = $connecteurFrequenceData->denomination ?: RootEntity::DENOMINATION;
        return "$connecteurFrequenceData->libelle [$denomination]";
    }

    public function getExpressionLabel(ConnecteurFrequenceData $connecteurFrequenceData): string
    {
        $line_list = $this->parseExpression($connecteurFrequenceData->expression);
        $label = '';
        foreach ($line_list as $line) {
            if ($line->frequence) {
                $unit = $line->in_seconds ? 'secondes' : 'minutes';
                $label .= $line->frequence === 1 ? "Toutes les $unit" : "Toutes les $line->frequence $unit";
            } elseif ($line->cron !== '') {
                $label .= "A ($line->cron)";
            }
            if ($line->nb_try) {
                $label .= " ($line->nb_try fois)";
            }
            $label .= "\n";
        }
        if (\end($line_list)->nb_try) {
            $label .= 'Suspendre le travail';
        }
        return $label;
    }

    /**
     * @return array{connecteur: string, instance: string, action: string, expression: string}
     */
    public function getLabels(ConnecteurFrequenceData $connecteurFrequenceData): array
    {
        return [
            'connecteur' => $this->getConnecteurLabel($connecteurFrequenceData),
            'instance' => $this->getInstanceLabel($connecteurFrequenceData),
            'action' => $this->getActionLabel($connecteurFrequenceData),
            'expression' => $this->getExpressionLabel($connecteurFrequenceData),
        ];
    }

    private function isMatching(
        array $connecteur_frequence_info,
        array $column_list,
        int $nb_kept,
        int $nb_connecteur_kept = PHP_INT_MAX,
    ): bool {
        foreach ($column_list as $i => $column) {
            $is_kept = $i < $nb_kept && ($i < $nb_connecteur_kept || $i >= \count(self::CONNECTEUR_COLUMN_LIST));
            if (!$connecteur_frequence_info[$column . ($is_kept ? '_match' : '_blank')]) {
                return false;
            }
        }
        return true;
    }

    private function findNearestOrDefault(ConnecteurFrequenceData $connecteurFrequenceData): ConnecteurFrequenceData
    {
        return $this->findNearest($connecteurFrequenceData)
            ?? $connecteurFrequenceData->withFrequency(self::DEFAULT_EXPRESSION, self::DEFAULT_ID_VERROU);
    }

    private function getCriteriaFromConnecteur(
        array $connecteur_info,
        ConnecteurFrequenceActionType $action_type,
        string $type_document = '',
        string $action = '',
    ): ConnecteurFrequenceData {
        return new ConnecteurFrequenceData(
            type_connecteur: $connecteur_info['global'] ?
                ConnecteurFrequenceType::GLOBAL->value : ConnecteurFrequenceType::ENTITE->value,
            famille_connecteur: (string)$connecteur_info['type'],
            id_connecteur: (string)$connecteur_info['id_connecteur'],
            id_ce: (int)$connecteur_info['id_ce'],
            action_type: $action_type->value,
            type_document: $type_document,
            action: $action,
        );
    }

    private function getJobConnecteurInfo(Job $job, string $type_document): ?array
    {
        if ($job->type === Job::TYPE_CONNECTEUR) {
            return $this->connecteurEntiteSQL->getInfo($job->id_ce) ?: null;
        }
        if ($type_document === '') {
            return null;
        }
        $connecteur_type = $this->documentTypeFactory->getFluxDocumentType($type_document)
            ->getAction()
            ->getProperties($job->etat_cible, 'connecteur-type');
        if (!$connecteur_type) {
            return null;
        }
        return $this->fluxEntiteSQL->getConnecteur($job->id_e, $type_document, $connecteur_type) ?: null;
    }

    /**
     * @return ConnecteurFrequenceExpressionLine[]
     */
    private function parseExpression(string $expression): array
    {
        $line_list = [];
        foreach (\explode("\n", $expression) as $line) {
            \preg_match('#([^X]*)\s*X?\s*(\d*)#', $line, $matches);
            $frequence_expression = \trim($matches[1]);
            $nb_try = (int)$matches[2];
            if (\preg_match('#\(([^)]*)\)#', $frequence_expression, $cron_matches)) {
                $line_list[] = new ConnecteurFrequenceExpressionLine($nb_try, cron: $cron_matches[1]);
                continue;
            }
            $line_list[] = new ConnecteurFrequenceExpressionLine(
                $nb_try,
                (int)$frequence_expression,
                \preg_match('/\d*\s*s/', $frequence_expression) === 1,
            );
        }
        return $line_list;
    }
}
