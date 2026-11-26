<?php

declare(strict_types=1);

namespace Pastell\Service\Agent;

use AgentSQL;
use CSV;
use EntiteSQL;
use Pastell\Service\Entite\RootEntity;

final class AgentImportService
{
    private const CSV_DELIMITER = ';';
    private const CSV_COLUMN_COUNT = 14;

    public function __construct(
        private readonly AgentSQL $agentSQL,
        private readonly EntiteSQL $entiteSQL,
        private readonly CSV $csv,
    ) {
    }

    public function import(string $file_path, int $id_e, bool $delete_all): int
    {
        $siren = null;
        if ($id_e !== RootEntity::ID_E) {
            $siren = $this->entiteSQL->getInfo($id_e)['siren'];
            $this->agentSQL->clean($siren);
        } elseif ($delete_all) {
            $this->agentSQL->cleanAll();
        }

        $nb_agent = 0;
        foreach ($this->csv->get($file_path, self::CSV_DELIMITER) as $agent_data) {
            if (\count($agent_data) !== self::CSV_COLUMN_COUNT) {
                continue;
            }
            $this->add($agent_data, $siren);
            $nb_agent++;
        }
        return $nb_agent;
    }

    public function add(array $agent_data, ?string $siren = null): void
    {
        if ($siren !== null) {
            $agent_data[AgentSQL::COLUMN_SIREN] = $siren;
        }
        $this->agentSQL->replace($agent_data);
    }
}
