<?php

declare(strict_types=1);

namespace Pastell\Tests\Command\Module;

use Pastell\Command\Module\ForceDeleteModuleCommand;
use Pastell\Service\Journal\JournalEntryService;
use Symfony\Component\Console\Tester\CommandTester;

class ForceDeleteModuleCommandTest extends \PastellTestCase
{
    private const ACTES_AUTOMATIQUE = 'actes-automatique';
    private CommandTester $commandTester;
    private \JobQueueSQL $jobQueueSQL;
    private \Job $job;
    private \DocumentSQL $documentSQL;
    private \FluxEntiteSQL $fluxEntiteSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobQueueSQL = $this->getObjectInstancier()->getInstance(\JobQueueSQL::class);
        $this->job = $this->getObjectInstancier()->getInstance(\Job::class);
        $this->documentSQL = $this->getObjectInstancier()->getInstance(\DocumentSQL::class);
        $this->fluxEntiteSQL = $this->getObjectInstancier()->getInstance(\FluxEntiteSQL::class);
        $command = new ForceDeleteModuleCommand(
            $this->getObjectInstancier()->getInstance(\DocumentEntite::class),
            $this->jobQueueSQL,
            $this->fluxEntiteSQL,
            $this->documentSQL,
            $this->getObjectInstancier()->getInstance(\DonneesFormulaireFactory::class),
            $this->getObjectInstancier()->getInstance(JournalEntryService::class),
        );
        $this->commandTester = new CommandTester($command);
    }

    private function executeCommand(
        string $module,
    ): int {
        return $this->commandTester->execute(
            [
                ForceDeleteModuleCommand::MODULE => $module,
            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function testDeleteModuleWithDocumentAndJob(): void
    {
        $id_d = $this->createDocument(self::ACTES_AUTOMATIQUE)['info'][ForceDeleteModuleCommand::ID_D];

        $this->job->type = \Job::TYPE_DOCUMENT;
        $this->job->id_e = $this::ID_E_COL;
        $this->job->id_d = $id_d;
        $this->job->id_u = $this::ID_U_ADMIN;
        $this->job->last_message = 'test';
        $this->job->etat_source = 'send-iparapheur';
        $this->job->etat_cible = 'verif-iparapheur';
        $now = date('Y-m-d H:i:s');
        $this->job->next_try = $now;
        $this->job->id_verrou = '';
        $id_job = $this->jobQueueSQL->createJob($this->job);

        $documents = $this->documentSQL->getAllByType(self::ACTES_AUTOMATIQUE);
        $docJob = $this->jobQueueSQL->getJob($id_job);
        self::assertSame($id_d, $documents[0][ForceDeleteModuleCommand::ID_D]);
        self::assertEquals($id_job, $docJob->id_job);

        $this->commandTester->setInputs(['o']);
        $this->executeCommand(self::ACTES_AUTOMATIQUE);

        self::assertEmpty($this->documentSQL->getAllByType(self::ACTES_AUTOMATIQUE));
        self::assertNull($this->jobQueueSQL->getJob($id_job));
    }

    public function testDeleteModuleWithAssociationsOnly(): void
    {
        $this->createConnecteurForTypeDossier(self::ACTES_AUTOMATIQUE, 'test');

        self::assertSame(
            [
                0 => [
                    'id_fe' => 10,
                    'id_e' => 1,
                    'flux' => self::ACTES_AUTOMATIQUE,
                    'id_ce' => 14,
                    'type' => 'test',
                    'num_same_type' => 0
                ]
            ],
            $this->fluxEntiteSQL->getAssociations(self::ACTES_AUTOMATIQUE)
        );

        $this->commandTester->setInputs(['o']);
        $this->executeCommand(self::ACTES_AUTOMATIQUE);

        self::assertEmpty($this->fluxEntiteSQL->getAssociations(self::ACTES_AUTOMATIQUE));
    }
}
