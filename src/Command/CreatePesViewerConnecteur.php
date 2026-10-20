<?php

namespace Pastell\Command;

use PastellBootstrap;
use PastellLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreatePesViewerConnecteur extends Command
{
    /**
     * @var PastellBootstrap
     */
    private $pastellBootstrap;
    private $pastellLogger;

    public function __construct(PastellBootstrap $pastellBootstrap, PastellLogger $pastellLogger)
    {
        parent::__construct();
        $this->pastellBootstrap = $pastellBootstrap;
        $this->pastellLogger = $pastellLogger;
    }

    protected function configure(): void
    {
        $this
            ->setName('app:create-pes-viewer-connecteur')
            ->setDescription('Create and associate a global PES Viewer connector if not exists')
            ->addArgument(
                'url_pes_viewer',
                InputArgument::OPTIONAL,
                'URL of PES Viewer (ex: https://127.0.0.1/)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->pastellLogger->enableStdOut(true);
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getDescription());
        $url_pes_viewer = $input->getArgument('url_pes_viewer') ?? "";
        $this->pastellBootstrap->installPESViewerConnecteur($url_pes_viewer);
        $io->success('Done');
        return 0;
    }
}
