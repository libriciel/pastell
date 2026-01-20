<?php

declare(strict_types=1);

namespace Pastell\Command\Connector;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use DonneesFormulaire;
use DonneesFormulaireFactory;
use Exception;
use FluxEntiteSQL;
use JsonException;
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
    public const string IPARAPHEUR_SOAP = 'iParapheur';
    public const string IPARAPHEUR_REST = 'iparapheur-rest';

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

        $connectorsIparapheurSOAP = $this->connecteurEntiteSQL->getAllEntiteConnectorById(self::IPARAPHEUR_SOAP);

        $specificIdCe = $input->getOption('id_ce');
        if ($specificIdCe) {
            $connectorsIparapheurSOAP = array_filter(
                $connectorsIparapheurSOAP,
                static fn($connector) => (string)$connector['id_ce'] === $specificIdCe
            );

            if (empty($connectorsIparapheurSOAP)) {
                $this->getIO()->error("Aucun connecteur iParapheur SOAP trouvé avec l'ID $specificIdCe");
                return self::FAILURE;
            }
        }

        $connectorsWithAssociations = [];
        $connectorsWithoutAssociationsCount = 0;

        foreach ($connectorsIparapheurSOAP as $connector) {
            $associations = $this->fluxEntiteSQL->getUsedByConnecteur($connector['id_ce']);
            if (!empty($associations)) {
                $connectorsWithAssociations[] = $connector;
            } else {
                $connectorsWithoutAssociationsCount++;
            }
        }

        $connectorsIparapheurSOAP = $connectorsWithAssociations;

        if ($connectorsWithoutAssociationsCount > 0) {
            $this->getIO()->note(
                "$connectorsWithoutAssociationsCount connecteur(s) sans association ont été exclus de la migration"
            );
        }

        if (empty($connectorsIparapheurSOAP)) {
            $this->getIO()->warning('Aucun connecteur iParapheur SOAP à migrer');
            return self::SUCCESS;
        }

        $this->displayConnectorsSummary($connectorsIparapheurSOAP);

        $connectorsNumber = count($connectorsIparapheurSOAP);
        if ($input->isInteractive()) {
            $question = $connectorsNumber === 1 ?
                'Voulez-vous migrer le connecteur ?'
                : "Voulez-vous migrer ces $connectorsNumber connecteur(s) ?";
            if (!$this->getIO()->confirm($question, false)) {
                return self::SUCCESS;
            }
        }

        $this->getIO()->section('Début de la migration');
        $results = $this->migrateConnectors($connectorsIparapheurSOAP);

        $this->displayMigrationResults($results);

        $successfulMigrations = array_filter($results, static fn($result) => $result['success']);

        if (!empty($successfulMigrations) && $input->isInteractive()) {
            $this->askForDeletion($successfulMigrations);
        }

        return self::SUCCESS;
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
            $soapForm = $this->connecteurFactory->getConnecteurConfig($connectorInfo['id_ce']);
            $wsdl = trim((string)$soapForm->get('iparapheur_wsdl', '"\''));
            $restUrl = preg_replace('#/ws-iparapheur\?wsdl$#', '', $wsdl);
            $soapUsername = (string)$soapForm->get('iparapheur_login');
            $soapPassword = (string)$soapForm->get('iparapheur_password');
            $soapType = (string)$soapForm->get('iparapheur_type');

            $tempConfig = $this->donneesFormulaireFactory->getNonPersistingDonneesFormulaire();
            $tempConfig->setData('url', $restUrl);
            $tempConfig->setData('username', $soapUsername);
            $tempConfig->setData('password', $soapPassword);
            $tempConnector = new IparapheurRestConnector($this->apiClientFactory);
            $tempConnector->setConnecteurConfig($tempConfig);

            $validationResult = $this->validateAndConfigureRestConnector(
                $tempConnector,
                $tempConfig,
                $soapType
            );

            if (!$validationResult['valid']) {
                throw new Exception($validationResult['error']);
            }

            $newIdCe = $this->connecteurCreationService->createConnecteur(
                self::IPARAPHEUR_REST,
                'signature',
                0,
                $connectorInfo['id_e'],
                0,
                $connectorInfo['libelle'] . ' (migré REST)',
                [],
                "Connecteur migré depuis iParapheur SOAP (id_ce: {$connectorInfo['id_ce']})"
            );

            $result['new_id_ce'] = $newIdCe;

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

            $result['associations_migrated'] = $this->connecteurAssociationService->migrateConnecteurAssociation(
                $connectorInfo['id_ce'],
                $newIdCe,
                0
            );

            $result['success'] = true;
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
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

                    $typeList = $connector->getTypeList();
                    $soapTypeName = trim($soapTypeName, '"\'');

                    $foundTypeId = null;
                    foreach ($typeList as $typeId => $typeName) {
                        if ($typeName === $soapTypeName) {
                            $foundTypeId = $typeId;
                            break;
                        }
                    }

                    if (!$foundTypeId) {
                        $result['error'] = "Type $soapTypeName non trouvé dans la liste REST";
                    } else {
                        $form->setData('iparapheur_type_id', $foundTypeId);
                        $form->setData('iparapheur_type', $soapTypeName);

                        $result['type_id'] = $foundTypeId;
                        $result['type_name'] = $soapTypeName;
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
