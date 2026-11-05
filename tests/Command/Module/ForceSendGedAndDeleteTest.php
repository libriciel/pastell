<?php

namespace Pastell\Tests\Command\Module;

use ConnecteurFactory;
use DocumentSQL;
use DonneesFormulaireFactory;
use EntiteSQL;
use Pastell\Service\Journal\JournalEntryService;
use Pastell\Command\Module\ForceSendGedAndDelete;
use Pastell\Service\Document\DocumentDeletionService;
use PastellTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ForceSendGedAndDeleteTest extends PastellTestCase
{
    private CommandTester $commandTester;
    private const SOURCE_MODULE = "test";
    private const CONNECTOR_TYPE = 'GED';


    protected function setUp(): void
    {
        parent::setUp();
        $command = new ForceSendGedAndDelete(
            $this->getObjectInstancier()->getInstance(DocumentSQL::class),
            $this->getObjectInstancier()->getInstance(entiteSQL::class),
            $this->getObjectInstancier()->getInstance(ConnecteurFactory::class),
            $this->getObjectInstancier()->getInstance(DonneesFormulaireFactory::class),
            $this->getObjectInstancier()->getInstance(JournalEntryService::class),
            $this->getObjectInstancier()->getInstance(DocumentDeletionService::class)
        );
        $this->commandTester = new CommandTester($command);
    }

    private function executeCommand(
        bool $includeSubEntities,
        string $confirm
    ): int {
        $this->commandTester->setInputs([$confirm]);
        return $this->commandTester->execute(
            [
                'sourceModule' => self::SOURCE_MODULE,
                'entityId' => self::ID_E_COL,
                '--includeSubEntities' => $includeSubEntities
            ]
        );
    }

    public function testCommand(): void
    {

        $this->assertSame(
            0,
            $this->executeCommand(true, "yes")
        );
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(sprintf(
            'There is no document `%s` for %s and sub entities',
            self::SOURCE_MODULE,
            'id_e=' . self::ID_E_COL
        ), $output);

        $this->createDocument(self::SOURCE_MODULE);
        $this->executeCommand(false, "yes");
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            '[OK] `app:module:force-send-ged-and-delete` 1 documents `test` for id_e=1',
            $output
        );
        $this->assertStringContainsString('Success for 0 and failure for 1', $output);
        $this->assertStringContainsString(
            '- id_e=1 (0/1) [ERROR] Connector GED not found for `test` id_e=1',
            $output
        );

        $connecteur_info = $this->createConnector("FakeGED", "connecteur de depot");
        $this->associateFluxWithConnector($connecteur_info['id_ce'], self::SOURCE_MODULE, self::CONNECTOR_TYPE);
        $this->executeCommand(false, "yes");
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString(
            '- id_e=1 (0/1) [ERROR] Connector GED invalid FakeGED for `test` id_e=1',
            $output
        );
    }

    public function testCommandNoConfirmation(): void
    {
        $this->createDocument(self::SOURCE_MODULE);
        $this->assertSame(
            1,
            $this->executeCommand(false, 'no')
        );
    }
}
