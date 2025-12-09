<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DonneesFormulaire;
use DonneesFormulaireFactory;
use Exception;
use FluxEntiteSQL;
use Pastell\Command\BaseCommand;
use Pastell\Connector\IparapheurRest\IpRestApiException;
use Pastell\Connector\IparapheurRest\IpRestException;
use Pastell\Connector\IparapheurRest\IparapheurRestConnector;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\ConnecteurCreationService;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function sprintf;

#[AsCommand(
    name: 'app:connector:migrate-ip-soap-rest',
    description: 'Migrer les connecteurs iParapheur (SOAP) vers iparapheur-rest (REST)',
)]
final class MigrateIparapheurSoapRest extends BaseCommand
{
    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly ConnecteurCreationService $connecteurCreationService,
        private readonly ConnecteurAssociationService $connecteurAssociationService,
        private readonly ConnecteurDeletionService $connecteurDeletionService,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly ApiClientFactory $apiClientFactory,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'id_ce',
                null,
                InputOption::VALUE_REQUIRED,
                'ID du connecteur spécifique à migrer (optionnel)'
            );
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getIO()->title('Migration des connecteurs iParapheur SOAP vers iparapheur-rest');

        $connectorsIparapheur = $this->connecteurEntiteSQL->getAllEntiteConnectorById('iParapheur');

        $specificIdCe = $input->getOption('id_ce');
        if ($specificIdCe) {
            $filteredConnectors = [];
            foreach ($connectorsIparapheur as $connector) {
                if ((string)$connector['id_ce'] === $specificIdCe) {
                    $filteredConnectors[] = $connector;
                }
            }
            $connectorsIparapheur = $filteredConnectors;

            if (empty($connectorsIparapheur)) {
                $this->getIO()->error("Aucun connecteur iParapheur trouvé avec l'ID $specificIdCe");
                return 1;
            }
        }

        $connectorsWithAssociations = [];
        $connectorsWithoutAssociations = 0;

        foreach ($connectorsIparapheur as $connector) {
            $associations = $this->fluxEntiteSQL->getUsedByConnecteur($connector['id_ce']);
            if (!empty($associations)) {
                $connectorsWithAssociations[] = $connector;
            } else {
                $connectorsWithoutAssociations++;
            }
        }

        $connectorsIparapheur = $connectorsWithAssociations;

        if ($connectorsWithoutAssociations > 0) {
            $this->getIO()->note(
                "$connectorsWithoutAssociations connecteur(s) sans association ont été exclus de la migration"
            );
        }

        if (empty($connectorsIparapheur)) {
            $this->getIO()->warning('Aucun connecteur iParapheur à migrer');
            return 0;
        }

        $this->displayConnectorsSummary($connectorsIparapheur);

        $connectorsNumber = count($connectorsIparapheur);
        if ($input->isInteractive()) {
            $question = "Voulez-vous migrer ces $connectorsNumber connecteur(s) ?";
            if (!$this->getIO()->confirm($question, false)) {
                return 0;
            }
        }

        $this->getIO()->section('Début de la migration');
        $results = $this->migrateConnectors($connectorsIparapheur);

        $this->displayMigrationResults($results);

        $successfulMigrations = [];
        foreach ($results as $result) {
            if ($result['success']) {
                $successfulMigrations[] = $result;
            }
        }

        if (!empty($successfulMigrations) && $input->isInteractive()) {
            $this->askForDeletion($successfulMigrations);
        }

        return 0;
    }

    private function displayConnectorsSummary(array $connectors): void
    {
        $this->getIO()->section('Connecteurs à migrer');

        $tableData = [];
        foreach ($connectors as $connector) {
            $associations = $this->fluxEntiteSQL->getUsedByConnecteur($connector['id_ce']);
            $associationsList = [];

            foreach ($associations as $assoc) {
                $associationsList[] = "{$assoc['flux']} (id_e: {$assoc['id_e']})";
            }

            $tableData[] = [
                $connector['id_ce'],
                $connector['denomination'] . " (id_e: {$connector['id_e']})",
                $connector['libelle'],
                count($associations),
                implode("\n", $associationsList) ?: '-'
            ];
        }

        $this->getIO()->table(
            ['id_ce', 'Entité', 'Libellé', 'Nb associations', 'Associations'],
            $tableData
        );
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function migrateConnectors(array $connectors): array
    {
        $results = [];
        $this->getIO()->progressStart(count($connectors));

        foreach ($connectors as $connector) {
            $result = $this->migrateOneConnector($connector);
            $results[] = $result;
            $this->getIO()->progressAdvance();
        }

        $this->getIO()->progressFinish();

        return $results;
    }

    /**
     * @throws ClientExceptionInterface
     */
    private function migrateOneConnector(array $connectorInfo): array
    {
        $result = [
            'id_ce' => $connectorInfo['id_ce'],
            'libelle' => $connectorInfo['libelle'],
            'entite' => $connectorInfo['denomination'],
            'success' => false,
            'new_id_ce' => null,
            'error' => null,
            'associations_migrated' => 0
        ];

        try {
            // Récupération de la config SOAP
            $soapForm = $this->connecteurFactory->getConnecteurConfig($connectorInfo['id_ce']);
            $wsdl = trim((string)$soapForm->get('iparapheur_wsdl', '"\''));
            $restUrl = preg_replace('#/ws-iparapheur\?wsdl$#', '', $wsdl);
            $soapUsername = (string)$soapForm->get('iparapheur_login');
            $soapPassword = (string)$soapForm->get('iparapheur_password');
            $soapType = (string)$soapForm->get('iparapheur_type');

            // Création d'un connecteur temporaire pour validation
            $tempConfig = $this->donneesFormulaireFactory->getNonPersistingDonneesFormulaire();
            $tempConfig->setData('url', $restUrl);
            $tempConfig->setData('username', $soapUsername);
            $tempConfig->setData('password', $soapPassword);
            $tempConnector = new IparapheurRestConnector($this->apiClientFactory);
            $tempConnector->setConnecteurConfig($tempConfig);

            // VALIDATION avec le connecteur temporaire
            $validationResult = $this->validateAndConfigureRestConnector(
                $tempConnector,
                $tempConfig,
                $soapType
            );

            if (!$validationResult['valid']) {
                throw new Exception($validationResult['error']);
            }

            // Validation réussie - Création du connecteur
            $newIdCe = $this->connecteurCreationService->createConnecteur(
                'iparapheur-rest',
                'signature',
                0,
                $connectorInfo['id_e'],
                0,
                $connectorInfo['libelle'] . ' (migré REST)',
                [],
                "Connecteur migré depuis iParapheur SOAP (id_ce: {$connectorInfo['id_ce']})"
            );

            $result['new_id_ce'] = $newIdCe;

            // Configuration du connecteur avec les données validées
            $restForm = $this->connecteurFactory->getConnecteurConfig($newIdCe);
            $restForm->setData('url', $restUrl);
            $restForm->setData('username', $soapUsername);
            $restForm->setData('password', $soapPassword);
            $restForm->setData('tenant_id', $validationResult['tenant_id']);
            $restForm->setData('tenant_name', $validationResult['tenant_name']);
            $restForm->setData('desk_id', $validationResult['desk_id']);
            $restForm->setData('desk_name', $validationResult['desk_name']);
            $restForm->setData('iparapheur_type_id', $validationResult['type_id']);
            $restForm->setData('iparapheur_type', $validationResult['type_name']);
            $restForm->setData('iparapheur_nb_jour_max', $soapForm->get('iparapheur_nb_jour_max'));
            $restForm->setData('iparapheur_metadata', $soapForm->get('iparapheur_metadata'));
            $restForm->setData('iparapheur_multi_doc', $soapForm->get('iparapheur_multi_doc'));

            // Migration des associations
            $associations = $this->fluxEntiteSQL->getUsedByConnecteur($connectorInfo['id_ce']);
            foreach ($associations as $association) {
                $this->connecteurAssociationService->addConnecteurAssociation(
                    $association['id_e'],
                    $newIdCe,
                    $association['type'],
                    0,
                    $association['flux'],
                    $association['num_same_type']
                );
            }

            $result['associations_migrated'] = count($associations);
            $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private function validateAndConfigureRestConnector(
        IparapheurRestConnector $connector,
        DonneesFormulaire $form,
        string $soapTypeName
    ): array {
        $result = [
            'valid' => false,
            'error' => null,
            'tenant_id' => null,
            'tenant_name' => null,
            'desk_id' => null,
            'desk_name' => null,
            'type_id' => null,
            'type_name' => null
        ];

        try {
            // Validation du tenant
            $tenantList = $connector->getTenantList();
            if (count($tenantList) === 0) {
                $result['error'] = 'Aucun tenant trouvé';
            } elseif (count($tenantList) > 1) {
                $result['error'] = 'Plusieurs tenants trouvés (' . count($tenantList) . '), impossible de déterminer automatiquement';
            } else {
                $tenantId = key($tenantList);
                $tenantName = current($tenantList);
                $form->setData('tenant_id', $tenantId);
                $form->setData('tenant_name', $tenantName);
                $connector->setConnecteurConfig($form);

                $result['tenant_id'] = $tenantId;
                $result['tenant_name'] = $tenantName;

                // Validation du desk
                $deskList = $connector->getDeskList();
                if (count($deskList) === 0) {
                    $result['error'] = 'Aucun desk trouvé';
                } elseif (count($deskList) > 1) {
                    $result['error'] = 'Plusieurs desks trouvés (' . count($deskList) . '), impossible de déterminer automatiquement';
                } else {
                    $deskId = key($deskList);
                    $deskName = current($deskList);
                    $form->setData('desk_id', $deskId);
                    $form->setData('desk_name', $deskName);
                    $connector->setConnecteurConfig($form);

                    $result['desk_id'] = $deskId;
                    $result['desk_name'] = $deskName;

                    // Validation du type
                    $typeList = $connector->getTypeList();
                    $soapTypeName = trim($soapTypeName, '"\'');

                    $foundTypeId = null;
                    $foundTypeName = null;
                    foreach ($typeList as $typeId => $typeName) {
                        if ($typeName === $soapTypeName) {
                            $foundTypeId = $typeId;
                            $foundTypeName = $typeName;
                            break;
                        }
                    }

                    if (!$foundTypeId) {
                        $result['error'] = "Type $soapTypeName non trouvé dans la liste REST";
                    } else {
                        $form->setData('iparapheur_type_id', $foundTypeId);
                        $form->setData('iparapheur_type', $foundTypeName);

                        $result['type_id'] = $foundTypeId;
                        $result['type_name'] = $foundTypeName;
                        $result['valid'] = true;
                    }
                }
            }
        } catch (IpRestApiException | IpRestException $e) {
            $result['error'] = 'Erreur API REST: ' . $e->getMessage();
        }

        return $result;
    }

    private function displayMigrationResults(array $results): void
    {
        $this->getIO()->section('Résultats de la migration');

        $successCount = 0;
        $errorCount = 0;

        foreach ($results as $result) {
            if ($result['success']) {
                $successCount++;
                $this->getIO()->success(
                    "✓ {$result['libelle']} (ID: {$result['id_ce']}) → Nouveau connecteur REST (ID: {$result['new_id_ce']}) - {$result['associations_migrated']} association(s) migrée(s)"
                );
            } else {
                $errorCount++;
                $this->getIO()->error(
                    "✗ {$result['libelle']} (ID: {$result['id_ce']}) - Erreur: {$result['error']}"
                );
            }
        }

        $this->getIO()->writeln('');
        $this->getIO()->writeln("<info>Résumé: $successCount succès, $errorCount erreur(s)</info>");
    }

    private function askForDeletion(array $successfulMigrations): void
    {
        $this->getIO()->section('Suppression des connecteurs SOAP');

        $this->getIO()->writeln('Connecteurs SOAP migrés avec succès:');
        foreach ($successfulMigrations as $result) {
            $this->getIO()->writeln(sprintf(
                '  - %s (ID SOAP: %d → ID REST: %d)',
                $result['libelle'],
                $result['id_ce'],
                $result['new_id_ce']
            ));
        }

        $this->getIO()->newLine();
        $this->getIO()->warning([
            'ATTENTION : Cette action va supprimer définitivement les connecteurs SOAP.',
            'Les connecteurs REST migrés seront conservés avec leurs associations.'
        ]);

        if (!$this->getIO()->confirm('Voulez-vous supprimer ces connecteurs iParapheur SOAP maintenant ?', false)) {
            return;
        }

        // Suppression des connecteurs SOAP
        $this->getIO()->progressStart(count($successfulMigrations));
        $deleted = 0;
        $errors = [];

        foreach ($successfulMigrations as $result) {
            $soapIdCe = $result['id_ce'];

            try {
                $this->connecteurDeletionService->disassociate($soapIdCe);
                $this->connecteurDeletionService->deleteConnecteur($soapIdCe);
                $deleted++;

                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->writeln("✓ Supprimé : {$result['libelle']} (ID: {$soapIdCe})");
                }
            } catch (Exception $e) {
                $errors[] = [
                    'connector' => $result['libelle'],
                    'id_ce' => $soapIdCe,
                    'error' => $e->getMessage()
                ];

                if ($this->getIO()->isVerbose()) {
                    $this->getIO()->error("✗ Erreur : {$result['libelle']} - {$e->getMessage()}");
                }
            }

            $this->getIO()->progressAdvance();
        }

        $this->getIO()->progressFinish();

        // Affichage des résultats
        if ($deleted > 0) {
            $this->getIO()->success(sprintf(
                '%d connecteur(s) SOAP supprimé(s) avec succès',
                $deleted
            ));
        }

        if (!empty($errors)) {
            $this->getIO()->error(sprintf(
                '%d erreur(s) lors de la suppression :',
                count($errors)
            ));

            foreach ($errors as $error) {
                $this->getIO()->writeln(sprintf(
                    '  - %s (ID: %d): %s',
                    $error['connector'],
                    $error['id_ce'],
                    $error['error']
                ));
            }
        }
    }
}
