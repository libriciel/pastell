<?php

declare(strict_types=1);

namespace Pastell\Command\Entity;

use ConnecteurEntiteSQL;
use DaemonManager;
use DaemonSQL;
use DocumentEntite;
use DocumentSQL;
use DonneesFormulaireFactory;
use EntiteSQL;
use Exception;
use FluxEntiteSQL;
use JobManager;
use NotFoundException;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use RoleUtilisateur;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use UnrecoverableException;
use UtilisateurListe;
use UtilisateurSQL;

#[AsCommand(
    name: 'app:entity:force-delete-entity',
    description: 'Deletes an entity and all its child entities (recursively),' .
    'along with all its documents, connectors, associations,' .
    'users, and any daemon. Use --do to actually execute (otherwise dry run).',
)]
class ForceDeleteEntity extends Command
{
    private const string ID_E = 'id_e';
    private const string ID_D = 'id_d';
    private const string ID_CE = 'id_ce';
    private const string ID_FE = 'id_fe';
    private const string ID_U = 'id_u';

    public function __construct(
        private readonly EntiteSQL $entiteSQL,
        private readonly DocumentEntite $documentEntite,
        private readonly DocumentSQL $document,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly UtilisateurListe $utilisateurListe,
        private readonly UtilisateurSQL $utilisateur,
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly JobManager $jobManager,
        private readonly ConnecteurDeletionService $connecteurDeletionService,
        private readonly ConnecteurAssociationService $connecteurAssociationService,
        private readonly DaemonManager $daemonManager,
        private readonly DaemonSQL $daemonSQL,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(self::ID_E, InputArgument::REQUIRED, "Identifiant de l'entité à supprimer")
            ->addOption(
                'do',
                null,
                InputOption::VALUE_NONE,
                'Effectuer réellement la suppression (par défaut: dry-run)'
            );
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id_e = (string)$input->getArgument(self::ID_E);
        $do = (bool)$input->getOption('do');

        if (!$this->entiteSQL->getInfo($id_e)) {
            $io->error("L'entité {$id_e} n'existe pas.");
            return Command::FAILURE;
        }

        $entite_list = $this->entiteSQL->getFille($id_e) ?? [];
        $id_e_list = array_reverse(array_map(static function ($a) {
            return $a[self::ID_E];
        }, $entite_list));
        $id_e_list [] = $id_e;

        $io->title('Suppression d’entité (dry-run: ' . ($do ? 'non' : 'oui') . ')');
        $io->writeln('Liste des entités à supprimer (ordre de traitement) :');
        $io->writeln(implode(', ', $id_e_list));
        $io->newLine();

        foreach ($id_e_list as $id_e) {
            $io->section("Traitement de l'entité {$id_e}");

            $daemon = $this->daemonSQL->getDaemonByEntity((int)$id_e);
            $io->note('Daemon détecté : ' . ($daemon !== null ? 'oui' : 'non'));
            if ($daemon !== null) {
                if ($do) {
                    $this->daemonManager->stopDaemon($daemon->id_daemon);
                    $this->daemonManager->removeDaemon($daemon->id_daemon);
                }
                $io->writeln("Suppression du daemon de l'entité {$id_e} : " . ($do ? '[OK]' : '[PASS]'));
            }

            $all_doc = $this->documentEntite->getAll($id_e);
            $id_d_list = array_map(static function ($a) {
                return $a[self::ID_D];
            }, $all_doc);

            $io->note('Documents à supprimer : ' . (empty($id_d_list) ? '[aucun]' : implode(', ', $id_d_list)));
            foreach ($id_d_list as $id_d) {
                if ($do) {
                    $this->donneesFormulaireFactory->get($id_d)->delete();
                    $this->document->delete($id_d);
                    $this->jobManager->deleteDocument($id_e, $id_d);
                }
                $io->writeln("Suppression du document {$id_d} : " . ($do ? '[OK]' : '[PASS]'));
            }

            $all_connecteur = $this->connecteurEntiteSQL->getAll($id_e);
            $id_ce_list = array_map(static function ($a) {
                return $a[self::ID_CE];
            }, $all_connecteur);
            $io->note('Connecteurs à supprimer : ' . (empty($id_ce_list) ? '[aucun]' : implode(', ', $id_ce_list)));
            foreach ($id_ce_list as $id_ce) {
                $all_flux = $this->fluxEntiteSQL->getUsedByConnecteur($id_ce);
                $id_fe_list = array_map(static function ($a) {
                    return $a[self::ID_FE];
                }, $all_flux);
                $io->text(
                    'Associations à supprimer : ' . (empty($id_fe_list) ? '[aucune]' : implode(', ', $id_fe_list))
                );
                foreach ($id_fe_list as $idFE) {
                    if ($do) {
                        $this->connecteurAssociationService->deleteConnecteurAssociationById_fe($idFE);
                    }
                    $io->writeln("Suppression de l'association {$idFE} : " . ($do ? '[OK]' : '[PASS]'));
                }
                if ($do) {
                    $this->connecteurDeletionService->deleteConnecteur($id_ce);
                }
                $io->writeln("Suppression du connecteur {$id_ce} : " . ($do ? '[OK]' : '[PASS]'));
            }

            $all_utilisateur = $this->utilisateurListe->getAllUtilisateurSimple($id_e);
            $id_u_list = array_map(static function ($a) {
                return $a[self::ID_U];
            }, $all_utilisateur);
            $io->note('Utilisateurs à supprimer : ' . (empty($id_u_list) ? '[aucun]' : implode(', ', $id_u_list)));
            foreach ($id_u_list as $id_u) {
                if ($do) {
                    $this->roleUtilisateur->removeAllRole($id_u);
                    $this->utilisateur->desinscription($id_u);
                }
                $io->writeln("Suppression de l'utilisateur {$id_u} : " . ($do ? '[OK]' : '[PASS]'));
            }

            if ($do) {
                $this->entiteSQL->removeEntite($id_e);
            }
            $io->writeln("Suppression de l'entité {$id_e} : " . ($do ? '[OK]' : '[PASS]'));
            $io->newLine();
        }

        $io->success(
            $do ? 'Suppressions effectuées.' : 'Dry-run terminé. Relancer avec --do pour exécuter réellement.'
        );
        return Command::SUCCESS;
    }
}
