<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use Exception;
use Pastell\Command\BaseCommand;
use Pastell\Storage\EmptyPasswordException;
use Pastell\Storage\MissingVaultException;
use Pastell\Storage\VaultIdNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:connector:retrieve-all-from-vault',
    description: 'Retrieves all passwords from the vault'
)]
class RetrievePasswordsFromVault extends BaseCommand
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
                        $donneesFormulaire->extractPasswordFromVault($passwordField);
                        $this->getIO()->success('Field ' . $passwordField->getName() . ' was extracted from the vault');
                    } catch (VaultIdNotFoundException $e) {
                        $this->getIO()->writeln('Field ' . $passwordField->getName() . ' is not stored in the vault.');
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
        $this->getIO()->success('Password fields have been extracted from the vault.');
        return self::SUCCESS;
    }
}
