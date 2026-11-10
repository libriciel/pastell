<?php

declare(strict_types=1);

namespace Pastell\Command\Daemon;

use Monolog\Logger;
use PastellDaemon;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Starts a worker and execute a job'
)]
final class StartWorker extends Command
{
    public const string NAME = 'app:daemon:start-worker';
    private const JOB = 'job';
    private PastellDaemon $daemon;

    public function __construct(
        private readonly \ObjectInstancier $objectInstancier,
    ) {
        /**
         * TODO: When everything is loaded by symfony autowiring, the correct logging channel should be injected
         */
        $this->objectInstancier->setInstance(
            Logger::class,
            $this->objectInstancier->getInstance(Logger::class)->withName('WORKER')
        );
        $this->daemon = $this->objectInstancier->getInstance(PastellDaemon::class);
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::JOB, InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->daemon->runningWorker($input->getArgument(self::JOB));
        return Command::SUCCESS;
    }
}
