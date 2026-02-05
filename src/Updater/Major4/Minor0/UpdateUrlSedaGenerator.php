<?php

declare(strict_types=1);

namespace Pastell\Updater\Major4\Minor0;

use ConnecteurEntiteSQL;
use DonneesFormulaireFactory;
use Pastell\Updater\Version;
use PastellLogger;

final class UpdateUrlSedaGenerator implements Version
{
    public function __construct(
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly PastellLogger $logger,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function update(): void
    {
        $this->logger->info('Start : Mise à jour des urls des générateurs SEDA de la 3.1');
        $connecteurSedaGlobaux = $this->connecteurEntiteSQL->getAllByConnecteurId('generateur-seda', true);
        foreach ($connecteurSedaGlobaux as $connecteurSedaGlobal) {
            $donneeFormulaire = $this->donneesFormulaireFactory
                ->getConnecteurEntiteFormulaire($connecteurSedaGlobal['id_ce']);
            $url = $donneeFormulaire->get('seda_generator_url');
            $donneeFormulaire->setData('seda_generator_url', 'http://seda-generator');
            $newUrl = $donneeFormulaire->get('seda_generator_url');
            $this->logger->info('Connecteur global : ' . $connecteurSedaGlobal['id_ce']
                . ', Url : ' . $url . ' -> ' . $newUrl);
        }

        $id_connecteurs = ['generateur-seda-asalae-1.0', 'generateur-seda-asalae-2.1'];
        foreach ($id_connecteurs as $id_connecteur) {
            $connecteurSEDAs = $this->connecteurEntiteSQL->getAllByConnecteurId($id_connecteur);
            foreach ($connecteurSEDAs as $connecteurSeda) {
                $donneeFormulaire = $this->donneesFormulaireFactory
                    ->getConnecteurEntiteFormulaire($connecteurSeda['id_ce']);
                $url = $donneeFormulaire->get('seda_generator_url');
                if ($url === 'http://localhost:8021' || $url === 'http://127.0.0.1:8021') {
                    $donneeFormulaire->setData('seda_generator_url', 'http://seda-generator');
                    $newUrl = $donneeFormulaire->get('seda_generator_url');
                    $this->logger->info('Connecteur : ' . $connecteurSeda['id_ce']
                        . ', Url : ' . $url . ' -> ' . $newUrl);
                }
            }
        }
    }
}
