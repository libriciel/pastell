<?php

namespace Pastell\Service\Connecteur;

use ConnecteurEntiteSQL;
use ConnecteurFrequenceSQL;
use DonneesFormulaireFactory;
use Exception;
use FluxEntiteSQL;
use JobManager;

class ConnecteurDeletionService
{
    private $connecteurEntiteSQL;
    private $connecteurActionService;
    private $donneesFormulaireFactory;
    private $fluxEntiteSQL;
    private $jobManager;
    private ConnecteurFrequenceSQL $connecteurFrequenceSQL;

    public function __construct(
        ConnecteurEntiteSQL $connecteurEntiteSQL,
        ConnecteurActionService $connecteurActionService,
        DonneesFormulaireFactory $donneesFormulaireFactory,
        FluxEntiteSQL $fluxEntiteSQL,
        JobManager $jobManager,
        ConnecteurFrequenceSQL $connecteurFrequenceSQL
    ) {
        $this->connecteurEntiteSQL = $connecteurEntiteSQL;
        $this->connecteurActionService = $connecteurActionService;
        $this->donneesFormulaireFactory = $donneesFormulaireFactory;
        $this->fluxEntiteSQL = $fluxEntiteSQL;
        $this->jobManager = $jobManager;
        $this->connecteurFrequenceSQL = $connecteurFrequenceSQL;
    }

    /**
     * @throws Exception
     */
    public function deleteConnecteur(int $id_ce): void
    {
        $id_used = $this->fluxEntiteSQL->getFluxByConnecteur($id_ce);
        if ($id_used) {
            throw new \RuntimeException("Ce connecteur est utilisé par des flux :  " . implode(", ", $id_used));
        }
        $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce)->delete();
        $this->connecteurEntiteSQL->delete($id_ce);
        $this->connecteurActionService->delete($id_ce);
        $this->connecteurFrequenceSQL->deleteByIdCe($id_ce);
        $this->jobManager->deleteConnecteur($id_ce);
    }

    public function disassociate(int $connectorId): void
    {
        foreach ($this->fluxEntiteSQL->getUsedByConnecteur($connectorId) as $association) {
            $this->fluxEntiteSQL->removeConnecteur($association['id_fe']);
        }
    }
}
