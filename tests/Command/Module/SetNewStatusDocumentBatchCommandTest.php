<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Module;

use Pastell\Command\Module\SetNewStatusDocumentBatchCommand;
use Pastell\Configuration\DocumentTypeValidation;
use Pastell\Service\Document\DocumentEntiteService;
use Symfony\Component\Console\Tester\CommandTester;

class SetNewStatusDocumentBatchCommandTest extends \PastellTestCase
{
    private const ACTES_GENERIQUE = 'actes-generique';
    private const MAILSEC = 'mailsec';
    private const LAST_ACTION = 'last_action';
    private const CREATION = 'creation';
    private const FATAL_ERROR = 'fatal-error';
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        $command = new SetNewStatusDocumentBatchCommand(
            $this->getObjectInstancier()->getInstance(\JobManager::class),
            $this->getObjectInstancier()->getInstance(\DocumentActionEntite::class),
            $this->getObjectInstancier()->getInstance(\ActionChange::class),
            $this->getObjectInstancier()->getInstance(\FluxDefinitionFiles::class),
            $this->getObjectInstancier()->getInstance(DocumentTypeValidation::class),
            $this->getObjectInstancier()->getInstance(\EntiteSQL::class),
        );
        $this->commandTester = new CommandTester($command);
    }

    private function executeCommand(
        int $id_e,
        string $type,
        string $oldStatus,
        string $newStatus,
    ): int {
        return $this->commandTester->execute(
            [
                SetNewStatusDocumentBatchCommand::ID_E => $id_e,
                SetNewStatusDocumentBatchCommand::TYPE => $type,
                SetNewStatusDocumentBatchCommand::OLD_STATUS => $oldStatus,
                SetNewStatusDocumentBatchCommand::NEW_STATUS => $newStatus
            ]
        );
    }

    public function testCommand(): void
    {
        $this->createDocument(self::ACTES_GENERIQUE);
        $this->createDocument(self::ACTES_GENERIQUE);
        $this->createDocument(self::MAILSEC);

        $documentEntiteService = $this->getObjectInstancier()->getInstance(DocumentEntiteService::class);
        $documents = $documentEntiteService->getDocuments(self::ID_E_COL);
        foreach ($documents as $document) {
            self::assertSame(self::CREATION, $document[self::LAST_ACTION]);
        }

        $this->commandTester->setInputs(['o']);
        $this->executeCommand(
            self::ID_E_COL,
            self::ACTES_GENERIQUE,
            self::CREATION,
            self::FATAL_ERROR
        );
        $documents = $documentEntiteService->getDocuments(self::ID_E_COL);
        foreach ($documents as $document) {
            if ($document['type'] === self::ACTES_GENERIQUE) {
                self::assertSame(self::FATAL_ERROR, $document[self::LAST_ACTION]);
            } elseif ($document['type'] === self::MAILSEC) {
                self::assertSame(self::CREATION, $document[self::LAST_ACTION]);
            }
        }
    }
}
