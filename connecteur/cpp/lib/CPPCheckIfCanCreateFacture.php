<?php

use Pastell\Service\ChorusPro\ChorusProImportUtilService;

class CPPCheckIfCanCreateFacture
{
    // #2467 : A_RECYCLER conservé pour les factures ayant encore ce statut courant sur Chorus Pro
    private array $statusBanned = [
        PortailFactureConnecteur::STATUT_A_RECYCLER,
        PortailFactureConnecteur::STATUT_SUSPENDUE
    ];

    public function canCreateFacture(array $factureChorus, string $dateLimiteDePriseEnCharge, array $statusCourants): bool
    {
        if (
            $this->dateStatusCourantOlderThanDateLimite($factureChorus['date_statut_courant'], $dateLimiteDePriseEnCharge)
        ) {
            return false;
        }

        if (
            $this->isFactureRecuAndHasNotStatutCourant($factureChorus['type_integration'], $factureChorus['statut'], $statusCourants)
        ) {
            return false;
        }

        if (
            $this->isFactureTravauxAndFactureHasBannedStatus($factureChorus['type_integration'], $factureChorus['statut'])
        ) {
            return false;
        }

        return true;
    }

    private function dateStatusCourantOlderThanDateLimite(string $dateStatutCourant, string $dateLimiteDePriseEnCharge): bool
    {
        return $dateStatutCourant < $dateLimiteDePriseEnCharge;
    }

    private function isFactureRecuAndHasNotStatutCourant(string $typeIntegration, string $statutFacture, array $statusCourants): bool
    {
        $isFactureRecu = $typeIntegration === ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE;
        $hasNotStatutCourant = !in_array($statutFacture, $statusCourants);

        return $isFactureRecu && $hasNotStatutCourant;
    }

    private function isFactureTravauxAndFactureHasBannedStatus(string $typeIntegration, string $statutFacture): bool
    {
        $isFactureTravaux = $typeIntegration === ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_CLE;
        $factureHasBannedStatus = in_array($statutFacture, $this->statusBanned);

        return $isFactureTravaux && $factureHasBannedStatus;
    }
}
