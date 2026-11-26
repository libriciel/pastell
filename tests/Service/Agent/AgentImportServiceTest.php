<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Agent;

use AgentSQL;
use Exception;
use Pastell\Service\Agent\AgentImportService;
use Pastell\Service\Entite\RootEntity;
use PastellTestCase;
use TmpFolder;

final class AgentImportServiceTest extends PastellTestCase
{
    private const SIREN_COL = '000000000';
    private const SIREN_OTHER = '111111118';

    private TmpFolder $tmpFolder;
    private string $tmp_folder;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpFolder = new TmpFolder();
        $this->tmp_folder = $this->tmpFolder->create();
    }

    protected function tearDown(): void
    {
        $this->tmpFolder->delete($this->tmp_folder);
        parent::tearDown();
    }

    private function getAgentImportService(): AgentImportService
    {
        return $this->getObjectInstancier()->getInstance(AgentImportService::class);
    }

    private function getAgentSQL(): AgentSQL
    {
        return $this->getObjectInstancier()->getInstance(AgentSQL::class);
    }

    private function getAgentData(string $matricule, string $siren): array
    {
        return [
            $matricule, 'M.', 'Bond', 'Bond', 'James', 'XYZ', 'Agent secret', 'UK', 'Royaume-Uni',
            $siren, '2', 'Libelle', '42', '43',
        ];
    }

    private function createCsv(array $line_list): string
    {
        $file_path = $this->tmp_folder . '/agent.csv';
        $csv_content = '';
        foreach ($line_list as $line) {
            $csv_content .= implode(';', $line) . "\n";
        }
        file_put_contents($file_path, $csv_content);
        return $file_path;
    }

    public function testImportForEntity(): void
    {
        $this->getAgentSQL()->replace($this->getAgentData('001', self::SIREN_COL));
        $file_path = $this->createCsv([
            $this->getAgentData('007', self::SIREN_OTHER),
            $this->getAgentData('008', self::SIREN_OTHER),
            ['invalid', 'line'],
        ]);

        self::assertSame(2, $this->getAgentImportService()->import($file_path, self::ID_E_COL, false));
        $matricule_list = array_column($this->getAgentSQL()->getAll(self::SIREN_COL), 'matricule');
        sort($matricule_list);
        self::assertSame(['007', '008'], $matricule_list);
        self::assertSame(0, $this->getAgentSQL()->getNbAgent(self::SIREN_OTHER));
    }

    public function testImportDeleteAll(): void
    {
        $this->getAgentSQL()->replace($this->getAgentData('001', self::SIREN_COL));
        $file_path = $this->createCsv([$this->getAgentData('007', self::SIREN_OTHER)]);

        self::assertSame(1, $this->getAgentImportService()->import($file_path, RootEntity::ID_E, true));
        self::assertSame(0, $this->getAgentSQL()->getNbAgent(self::SIREN_COL));
        self::assertSame(1, $this->getAgentSQL()->getNbAgent(self::SIREN_OTHER));
    }

    public function testImportKeepExisting(): void
    {
        $this->getAgentSQL()->replace($this->getAgentData('001', self::SIREN_COL));
        $file_path = $this->createCsv([$this->getAgentData('007', self::SIREN_OTHER)]);

        $this->getAgentImportService()->import($file_path, RootEntity::ID_E, false);
        self::assertSame(1, $this->getAgentSQL()->getNbAgent(self::SIREN_COL));
        self::assertSame(1, $this->getAgentSQL()->getNbAgent(self::SIREN_OTHER));
    }

    public function testAddWithSiren(): void
    {
        $this->getAgentImportService()->add($this->getAgentData('007', self::SIREN_COL), self::SIREN_OTHER);
        self::assertSame(0, $this->getAgentSQL()->getNbAgent(self::SIREN_COL));
        self::assertSame(1, $this->getAgentSQL()->getNbAgent(self::SIREN_OTHER));
    }
}
