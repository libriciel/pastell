<?php

namespace PHPUnit\connecteur\cpp\lib;

use ExtensionCppTestCase;
use lib\CPPCheckIfCanCreateFacture;
use Pastell\Service\ChorusPro\ChorusProImportUtilService;
use PortailFactureConnecteur;

class CPPCheckIfCanCreateFactureTest extends ExtensionCppTestCase
{
    private CPPCheckIfCanCreateFacture $checkIfCanCreateFacture;
    private string $dateLimiteDePriseEnCharge;
    private array $statusCourant = [
        PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
        PortailFactureConnecteur::STATUT_SERVICE_FAIT,
        PortailFactureConnecteur::STATUT_MANDATEE,
        PortailFactureConnecteur::STATUT_COMPLETEE
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->checkIfCanCreateFacture = $this->getObjectInstancier()->getInstance(CPPCheckIfCanCreateFacture::class);
        $this->dateLimiteDePriseEnCharge = date('Y-m-d', strtotime('-30 days'));
    }

    public function testCanCreateFacture()
    {
        $dateStatutCourant = date('Y-m-d', strtotime('+5 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE,
            'statut' => PortailFactureConnecteur::STATUT_COMPLETEE
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture($fakeFactureChorus, $this->dateLimiteDePriseEnCharge, $this->statusCourant);

        self::assertTrue($canCreateFacture);
    }

    public function testCannotCreateFactureBecauseStatusCourantOlderThanDateLimite()
    {
        $dateStatutCourant = date('Y-m-d', strtotime('-50 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE,
            'statut' => PortailFactureConnecteur::STATUT_COMPLETEE
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture($fakeFactureChorus, $this->dateLimiteDePriseEnCharge, $this->statusCourant);

        self::assertFalse($canCreateFacture);
    }

    public function testCannotCreateFactureBecauseisFactureRecuAndHasNotStatutCourant()
    {
        $dateStatutCourant = date('Y-m-d', strtotime('+5 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE,
            'statut' => PortailFactureConnecteur::STATUT_A_RECYCLER
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture($fakeFactureChorus, $this->dateLimiteDePriseEnCharge, $this->statusCourant);

        self::assertFalse($canCreateFacture);
    }

    public function testCannotCreateFactureBecauseisFactureTravauxAndFactureHasBannedStatus()
    {
        $dateStatutCourant = date('Y-m-d', strtotime('+5 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_CLE,
            'statut' => PortailFactureConnecteur::STATUT_A_RECYCLER
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture($fakeFactureChorus, $this->dateLimiteDePriseEnCharge, $this->statusCourant);

        self::assertFalse($canCreateFacture);
    }
}
