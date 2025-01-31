<?php

namespace Pastell\Command\Connector;

use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use ConnecteurFactory;
use Exception;
use FluxEntiteSQL;
use Pastell\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReplaceGedSshWithDepotSftp extends BaseCommand
{
    /**
     * @var ConnecteurFactory
     */
    private $connectorFactory;
    /**
     * @var ConnecteurCreationService
     */
    private $connecteurCreationService;
    /**
     * @var ConnecteurAssociationService
     */
    private $connecteurAssociationService;
    /**
     * @var FluxEntiteSQL
     */
    private $fluxEntiteSQL;

    public function __construct(
        ConnecteurFactory $connectorFactory,
        ConnecteurCreationService $connecteurCreationService,
        ConnecteurAssociationService $connecteurAssociationService,
        FluxEntiteSQL $fluxEntiteSQL
    ) {
        $this->connectorFactory = $connectorFactory;
        $this->connecteurCreationService = $connecteurCreationService;
        $this->connecteurAssociationService = $connecteurAssociationService;
        $this->fluxEntiteSQL = $fluxEntiteSQL;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:connector:replace-ged-ssh-with-depot-sftp')
            ->setDescription('Replace associated ged-ssh connectors with depot-sftp.')
        ;
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getIO()->title("Start replacing associated `ged-ssh` connectors with `depot-sftp`");
        $associatedConnectors = $this->fluxEntiteSQL->getAssociatedConnectorsById('ged-ssh');
        $connectorsNumber = count($associatedConnectors);
        if ($input->isInteractive()) {
            $question = "There are $connectorsNumber associated connectors, do you want to continue ?";
            if (!$this->getIO()->confirm($question, false)) {
                return 0;
            }
        }
        $this->getIO()->progressStart($connectorsNumber);
        foreach ($associatedConnectors as $associatedConnector) {
            $gedSsh = $this->connectorFactory->getConnecteurById($associatedConnector['id_ce']);
            $gedSshInfo = $gedSsh->getConnecteurInfo();
            $gedSshForm = $this->connectorFactory->getConnecteurConfig($associatedConnector['id_ce']);

            $depotSftpId = $this->createAndConfigureDepotSftp(
                $associatedConnector['id_e'],
                $gedSshInfo['libelle'],
                $gedSshForm
            );

            $associationsOfConnector = $this->fluxEntiteSQL->getUsedByConnecteur($associatedConnector['id_ce']);
            foreach ($associationsOfConnector as $association) {
                $this->connecteurAssociationService->addConnecteurAssociation(
                    $association['id_e'],
                    $depotSftpId,
                    $association['type'],
                    0,
                    $association['flux'],
                    $association['num_same_type']
                );
            }
            $this->getIO()->progressAdvance();
        }

        $this->getIO()->progressFinish();
        $this->getIO()->success('Done');

        return 0;
    }

    /**
     * @throws Exception
     */
    protected function createAndConfigureDepotSftp(int $entityId, string $label, \DonneesFormulaire $gedSshForm): int
    {
        $depotSftpId = $this->connecteurCreationService->createConnecteur(
            'depot-sftp',
            'GED',
            0,
            $entityId,
            0,
            $label,
            [],
            "Le connecteur depot-sftp « $label » a été créé via la commande ReplaceGedSshWithDepotSftp"
        );

        $depotSftp = $this->connectorFactory->getConnecteurById($depotSftpId);
        $depotSftpForm = $this->connectorFactory->getConnecteurConfig($depotSftpId);
        $depotSftpForm->setData('depot_sftp_host', $gedSshForm->get('ssh_server'));
        $depotSftpForm->setData('depot_sftp_port', $gedSshForm->get('ssh_port'));
        $depotSftpForm->setData('depot_sftp_login', $gedSshForm->get('ssh_login'));
        $depotSftpForm->setData('depot_sftp_password', $gedSshForm->get('ssh_password'));
        $depotSftpForm->setData('depot_sftp_fingerprint', $gedSshForm->get('ssh_fingerprint'));
        $depotSftpForm->setData('depot_sftp_directory', $gedSshForm->get('ssh_directory'));

        $transfertMethod = $gedSshForm->get('ssh_mode_transfert');
        if ($transfertMethod == '1') {
            $depotSftpForm->setData('depot_type_depot', 1);
            $depotSftpForm->setData('depot_titre_repertoire', 1);
            $depotSftpForm->setData('depot_pastell_file_filename', 1);
        } elseif ($transfertMethod == '2') {
            $depotSftpForm->setData('depot_type_depot', 1);
            $depotSftpForm->setData('depot_titre_repertoire', 1);
            $depotSftpForm->setData('depot_pastell_file_filename', 2);
        } else {
            $depotSftpForm->setData('depot_type_depot', 3);
        }
        $depotSftpForm->setData('depot_metadonnees', 2);
        $depotSftp->setConnecteurConfig($depotSftpForm);
        return $depotSftpId;
    }
}
