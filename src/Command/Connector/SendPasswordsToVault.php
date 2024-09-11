<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use Exception;
use Pastell\Command\BaseCommand;
use Pastell\Storage\EmptyPasswordException;
use Pastell\Storage\MissingVaultException;
use Pastell\Storage\VaultPasswordAlreadyStoredException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:connector:send-all-to-vault',
    description: 'Sends all passwords to the vault'
)]
class SendPasswordsToVault extends BaseCommand
{
    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly ConnecteurFactory $connecteurFactory,
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run - will not dissociate anything');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $infosConnecteurs = $this->connecteurEntiteSQL->getAllForPlateform();
        foreach ($infosConnecteurs as $infosConnecteur) {
            $donneesFormulaire = $this->connecteurFactory->getConnecteurConfig($infosConnecteur['id_ce']);
            $passwordFields = $donneesFormulaire->getPasswordFields();
            if ($passwordFields) {
                $this->getIO()->section($infosConnecteur['id_ce'] . ' - ' . $infosConnecteur['libelle']);
                foreach ($passwordFields as $passwordField) {
                    try {
                        $donneesFormulaire->savePasswordValue($passwordField);
                        $this->getIO()->success('Field ' . $passwordField->getName() . ' was sent to the vault');
                    } catch (VaultPasswordAlreadyStoredException $e) {
                        $this->getIO()->writeln('Field ' . $passwordField->getName() . ' is already in the vault.');
                    } catch (EmptyPasswordException) {
                        $this->getIO()->writeln('Field ' . $passwordField->getName() . ' is empty.');
                    } catch (MissingVaultException) {
                        $this->getIO()->error('Vault is not configurated.');
                        return self::FAILURE;
                    }
                }
            }
        }

        $this->getIO()->newLine();
        $this->getIO()->success('Password fields have been sent to the vault.');
        return self::SUCCESS;
    }
}
