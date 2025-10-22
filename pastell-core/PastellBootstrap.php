<?php

use Pastell\Service\Connecteur\ConnecteurCreationService;

class PastellBootstrap
{
    /**
     * TODO: ObjectInstancier should not be injected
     * We should have a collection of classes to bootstrap the app instead of a single which does everything
     */
    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
        private readonly PastellLogger $pastellLogger,
        private readonly string $pastell_path,
    ) {
    }

    public function bootstrap(): void
    {
        try {
            $this->installHorodateur();
            $this->installCloudooo();
            $this->installPESViewerConnecteur();
            $this->installConnecteurFrequenceDefault();
            $this->rebuildTypeDossierPersonnalise();
            $this->flushRedis();
        } catch (Exception $e) {
            $this->pastellLogger->emergency("Erreur : " . $e->getMessage());
            $this->pastellLogger->emergency($e->getTraceAsString());
        }
    }

    private function getHostname(bool $isMailsec = false): string
    {
        return parse_url(
            $this->objectInstancier->getInstance($isMailsec ? 'websec_base' : 'site_base'),
            PHP_URL_HOST
        );
    }

    /**
     * @throws Exception
     */
    public function installHorodateur()
    {

        $connecteurCreationService = $this->objectInstancier->getInstance(ConnecteurCreationService::class);

        if ($connecteurCreationService->hasConnecteurGlobal(Horodateur::CONNECTEUR_TYPE_ID)) {
            $this->pastellLogger->info("Le connecteur d'horodatage est configuré");
            return;
        }

        $this->pastellLogger->info("Configuration d'un connecteur d'horodatage interne");
        $hostname = $this->getHostname();

        $tmpFile = new TmpFile();
        $key_file = $tmpFile->create();
        $cert_file = $tmpFile->create();

        $script = \sprintf(
            'bash %s/docker/generate-timestamp-certificate.sh %s %s %s 2>&1',
            $this->pastell_path,
            $hostname,
            $key_file,
            $cert_file
        );

        exec("$script ", $output, $return_var);
        $this->pastellLogger->info(implode("\n", $output));
        if ($return_var != 0) {
            throw new UnrecoverableException("Impossible de générer le certificat du timestamp !");
        }

        $id_ce = $connecteurCreationService->createAndAssociateGlobalConnecteur(
            'horodateur-interne',
            Horodateur::CONNECTEUR_TYPE_ID,
            'Horodateur interne par défaut'
        );

        $donneesFormulaireFactory = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class);
        $donneesFormulaire = $donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);

        $donneesFormulaire->addFileFromCopy(
            'signer_certificate',
            'timestamp-certificate.pem',
            $cert_file
        );
        $donneesFormulaire->addFileFromCopy(
            'signer_key',
            'timestamp-key.pem',
            $key_file
        );
        $donneesFormulaire->addFileFromCopy(
            'ca_certificate',
            'timestamp-certificate.pem',
            $cert_file
        );

        $this->pastellLogger->info("Horodateur interne installé et configuré avec un nouveau certificat autosigné");
    }

    /**
     * @deprecated 5.0.0
     */
    /**
     * @param string $server_name
     * @throws Exception
     */
    public function installCloudooo(string $server_name = "cloudooo")
    {
        $connecteurCreationService = $this->objectInstancier->getInstance(ConnecteurCreationService::class);

        if ($connecteurCreationService->hasConnecteurGlobal(ConvertisseurPDF::CONNECTEUR_TYPE_ID)) {
            $this->pastellLogger->info("Le connecteur de conversion Office vers PDF est configuré");
            return;
        }

        $id_ce = $connecteurCreationService->createAndAssociateGlobalConnecteur(
            'cloudooo',
            ConvertisseurPDF::CONNECTEUR_TYPE_ID,
            'Conversion Office PDF',
            [
                'cloudooo_hostname' => $server_name,
                'cloudooo_port' => '8011',
            ]
        );

        $this->pastellLogger->info("Le connecteur de conversion Office vers PDF a été configuré sur l'hote $server_name et le port 8011");
    }

    /**
     * @param string $url_pes_viewer
     * @throws Exception
     */
    public function installPESViewerConnecteur(string $url_pes_viewer = ""): void
    {
        if (! $url_pes_viewer) {
            $url_pes_viewer = $this->objectInstancier->getInstance('site_base');
        }

        $connecteurCreationService = $this->objectInstancier->getInstance(ConnecteurCreationService::class);

        if ($connecteurCreationService->hasConnecteurGlobal(PESViewer::CONNECTEUR_TYPE_ID)) {
            $this->pastellLogger->info("Le connecteur de PES viewer est déjà configuré");
            return;
        }

        $id_ce = $connecteurCreationService->createAndAssociateGlobalConnecteur(
            'pes-viewer',
            PESViewer::CONNECTEUR_TYPE_ID,
            '',
            ['url' => $url_pes_viewer]
        );

        $this->pastellLogger->info("Le connecteur de visualisation de PES a été installé sur l'URL $url_pes_viewer");
    }

    public function installConnecteurFrequenceDefault()
    {
        $connecteurFrequenceSQL = $this->objectInstancier->getInstance(ConnecteurFrequenceSQL::class);

        $connecteurFrequence = new ConnecteurFrequence();
        $nearest = $connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence);
        //Si aucune fréquence ne correspond à un connecteur par défaut
        if (!$nearest) {
            $defaultFrequencies = $this->getDefaultFrequencies();
            foreach ($defaultFrequencies as $name => $frequency) {
                $connecteurFrequence = new ConnecteurFrequence($frequency);
                $connecteurFrequenceSQL->edit($connecteurFrequence);
                $this->pastellLogger->info(
                    sprintf(
                        "Initialisation d'un connecteur `%s` avec la fréquence `%s`",
                        $name,
                        $frequency['expression']
                    )
                );
            }
        }
    }

    /**
     * @throws Exception
     */
    public function rebuildTypeDossierPersonnalise()
    {
        $typeDossierService = $this->objectInstancier->getInstance(TypeDossierService::class);
        $typeDossierService->rebuildAll();
    }

    public function flushRedis()
    {
        $this->pastellLogger->info("Vidage du cache");
        $redisWrapper = $this->objectInstancier->getInstance(MemoryCache::class);
        $redisWrapper->flushAll();
        $this->pastellLogger->info("Le cache a été vidé");
    }

    public function getDefaultFrequencies(): iterable
    {
        yield 'base' => [
            'expression' => '15',
        ];
        yield 'iparapheur' => [
            'expression' => '30',
            'type_connecteur' => ConnecteurFrequence::TYPE_ENTITE,
            'famille_connecteur' => 'signature',
            'id_connecteur' => 'iParapheur',
            'action_type' => ConnecteurFrequence::TYPE_ACTION_DOCUMENT,
            'id_verrou' => 'IPARAPHEUR',
        ];
        yield 'SAE' => [
            'expression' => "60 X 24\n1440",
            'type_connecteur' => ConnecteurFrequence::TYPE_ENTITE,
            'famille_connecteur' => 'SAE',
            'action_type' => ConnecteurFrequence::TYPE_ACTION_DOCUMENT,
            'id_verrou' => 'SAE',
        ];
        yield 'mailsec' => [
            'expression' => '1440',
            'type_connecteur' => ConnecteurFrequence::TYPE_ENTITE,
            'famille_connecteur' => 'mailsec',
            'action_type' => ConnecteurFrequence::TYPE_ACTION_DOCUMENT,
            'id_verrou' => 'MAILSEC_RELANCE',
        ];
        yield 'tdt entité' => [
            'expression' => '30',
            'type_connecteur' => ConnecteurFrequence::TYPE_ENTITE,
            'famille_connecteur' => 'TdT',
            'action_type' => ConnecteurFrequence::TYPE_ACTION_DOCUMENT,
            'id_verrou' => 'TDT',
        ];
        yield 'cpp entité' => [
            'expression' => '30',
            'type_connecteur' => ConnecteurFrequence::TYPE_ENTITE,
            'famille_connecteur' => 'PortailFacture',
            'id_connecteur' => 'cpp',
            'action_type' => ConnecteurFrequence::TYPE_ACTION_CONNECTEUR,
            'id_verrou' => 'CHORUS',
        ];
        yield 'purge' => [
            'expression' => '(0 22 * * *)',
            'type_connecteur' => ConnecteurFrequence::TYPE_ENTITE,
            'famille_connecteur' => 'Purge',
            'id_connecteur' => 'purge',
            'action_type' => ConnecteurFrequence::TYPE_ACTION_CONNECTEUR,
            'id_verrou' => 'PURGE',
        ];
        yield 'tdt global' => [
            'expression' => '(30 9 * * *)',
            'type_connecteur' => ConnecteurFrequence::TYPE_GLOBAL,
            'famille_connecteur' => 'TdT',
            'id_verrou' => 'TDT_GLOBAL',
        ];
        yield 'cpp global' => [
            'expression' => '1440',
            'type_connecteur' => ConnecteurFrequence::TYPE_GLOBAL,
            'famille_connecteur' => 'PortailFacture',
            'id_verrou' => 'CHORUS_GLOBAL',
        ];
    }
}
