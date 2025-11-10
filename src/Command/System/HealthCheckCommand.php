<?php

namespace Pastell\Command\System;

use Pastell\System\HealthCheck;
use Pastell\System\HealthCheckItem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UnrecoverableException;

class HealthCheckCommand extends Command
{
    /**
     * @var HealthCheck
     */
    private $healthCheck;

    /**
     * @var int
     */
    private $returnCode = 0;

    public function __construct(HealthCheck $healthCheck)
    {
        parent::__construct();
        $this->healthCheck = $healthCheck;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:system:healthcheck')
            ->setDescription('Vérification du test du système');
    }

    /**
     * @throws UnrecoverableException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $this
            ->checkWorkspace($output)
            ->checkJournal($output)
            ->checkRedis($output)
            ->checkPhpConfiguration($output)
            ->checkPhpExtensions($output)
            ->checkExpectedElements($output)
            ->checkCommands($output)
            ->checkConstants($output)
            ->checkAutoTest($output)
            ->checkPack($output);

        if ($this->returnCode === 0) {
            $io->success('Done');
        } else {
            $io->error('Error');
        }

        return $this->returnCode;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkPhpExtensions(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaders(['<options=bold,underscore>Extensions PHP</>'])
            ->setHorizontal(true);

        foreach ($this->healthCheck->check(HealthCheck::PHP_EXTENSIONS_CHECK) as $extension) {
            $table->addRow([$this->getResult($extension)]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkWorkspace(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Workspace')
            ->setHeaders(['', '']);
        foreach ($this->healthCheck->check(HealthCheck::WORKSPACE_CHECK) as $workspace) {
            $result = $this->getResult($workspace);
            $table->addRow([$workspace->label, $result]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkJournal(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Journal')
            ->setHeaders(['', '']);
        foreach ($this->healthCheck->check(HealthCheck::JOURNAL_CHECK) as $journal) {
            $result = $this->getResult($journal);
            $table->addRow([$journal->label, $result]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkRedis(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Redis')
            ->setHeaders(['', '']);
        foreach ($this->healthCheck->check(HealthCheck::REDIS_CHECK) as $redis) {
            $result = $this->getResult($redis);
            $table->addRow([$redis->label, $result]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkPhpConfiguration(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Configuration PHP')
            ->setHeaders(['clé', 'Valeurs minimums attendues', 'Valeurs présentes']);
        foreach ($this->healthCheck->check(HealthCheck::PHP_CONFIGURATION_CHECK) as $conf) {
            $result = $this->getResult($conf);
            $table->addRow([$conf->label, $conf->expectedValue, $result]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkExpectedElements(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Éléments attendus')
            ->setHeaders(['Élément', 'Attendu', 'Trouvé', 'Description']);
        foreach ($this->healthCheck->check(HealthCheck::EXPECTED_ELEMENTS_CHECK) as $expectedElement) {
            $result = $this->getResult($expectedElement);
            $table->addRow(
                [$expectedElement->label, $expectedElement->expectedValue, $result, $expectedElement->description]
            );
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkCommands(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Commande présente')
            ->setHeaders(['Commande', 'Résultat']);
        foreach ($this->healthCheck->check(HealthCheck::COMMAND_CHECK) as $command) {
            $result = $this->getResult($command);
            $table->addRow([$command->label, $result]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkConstants(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Constante')
            ->setHeaders(['Élément', 'Valeur']);
        foreach ($this->healthCheck->check(HealthCheck::CONSTANTS_CHECK) as $redis) {
            $table->addRow([$redis->label, $redis->result]);
        }

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkAutoTest(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaderTitle('Auto test')
            ->setHeaders(['', '']);

        $databaseSchema = $this->healthCheck->check(HealthCheck::DATABASE_SCHEMA_CHECK)[0];
        $encodingDatabase = $this->healthCheck->check(HealthCheck::DATABASE_ENCODING_CHECK)[0];
        $crashedTables = $this->healthCheck->check(HealthCheck::CRASHED_TABLES_CHECK)[0];
        $daemonHealth = $this->healthCheck->check(HealthCheck::DAEMON_CHECK)[0];
        $missingConnectors = $this->healthCheck->check(HealthCheck::MISSING_CONNECTORS_CHECK)[0];
        $missingModules = $this->healthCheck->check(HealthCheck::MISSING_MODULES_CHECK)[0];

        $table
            ->addRow([$databaseSchema->label, $this->getResult($databaseSchema)])
            ->addRow([$encodingDatabase->label, $this->getResult($encodingDatabase)])
            ->addRow([$crashedTables->label, $this->getResult($crashedTables)])
            ->addRow([$daemonHealth->label, $this->getResult($daemonHealth)])
            ->addRow([$missingConnectors->label, $this->getResult($missingConnectors)])
            ->addRow([$missingModules->label, $this->getResult($missingModules)]);

        $table->render();
        return $this;
    }

    /**
     * @throws UnrecoverableException
     */
    private function checkPack(OutputInterface $output): self
    {
        $table = new Table($output);
        $table
            ->setHeaders(['<options=bold,underscore>Pack(s)</>'])
            ->setHorizontal(false);
        foreach ($this->healthCheck->check(HealthCheck::PACK_CHECK) as $pack) {
            $table->addRow([$pack->label]);
        }
        $table->render();
        return $this;
    }

    private function getResult(HealthCheckItem $healthCheckItem): string
    {
        if ($healthCheckItem->isInfo()) {
            $result = $healthCheckItem->result;
        } elseif ($healthCheckItem->isSuccess()) {
            $result = "<info>$healthCheckItem->result</info>";
        } else {
            $result = "<error>$healthCheckItem->result</error>";
            $this->returnCode = 1;
        }
        return $result;
    }
}
