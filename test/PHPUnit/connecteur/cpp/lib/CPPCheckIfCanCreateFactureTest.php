<?php

namespace PHPUnit\connecteur\cpp\lib;

use CPPCheckIfCanCreateFacture;
use ExtensionCppTestCase;
use Pastell\Service\ChorusPro\ChorusProImportUtilService;
use PortailFactureConnecteur;

class CPPCheckIfCanCreateFactureTest extends ExtensionCppTestCase
{
    private readonly CPPCheckIfCanCreateFacture $checkIfCanCreateFacture;
    private readonly string $dateLimiteDePriseEnCharge;
    private readonly array $statusCourant;


    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->checkIfCanCreateFacture = $this->getObjectInstancier()->getInstance(CPPCheckIfCanCreateFacture::class);
        $this->dateLimiteDePriseEnCharge = date('Y-m-d', strtotime('-30 days'));
        $this->statusCourant = PortailFactureConnecteur::getListeStatutCourant();
    }

    public function testCanCreateFacture(): void
    {
        $dateStatutCourant = date('Y-m-d', strtotime('+5 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE,
            'statut' => PortailFactureConnecteur::STATUT_COMPLETEE
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture(
            $fakeFactureChorus,
            $this->dateLimiteDePriseEnCharge,
            $this->statusCourant
        );

        self::assertTrue($canCreateFacture);
    }

    public function testCannotCreateFactureBecauseStatusCourantOlderThanDateLimite(): void
    {
        $dateStatutCourant = date('Y-m-d', strtotime('-50 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE,
            'statut' => PortailFactureConnecteur::STATUT_COMPLETEE
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture(
            $fakeFactureChorus,
            $this->dateLimiteDePriseEnCharge,
            $this->statusCourant
        );

        self::assertFalse($canCreateFacture);
    }

    public function testCannotCreateFactureBecauseIsFactureRecuAndHasNotStatutCourant(): void
    {
        $dateStatutCourant = date('Y-m-d', strtotime('+5 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_CLE,
            'statut' => PortailFactureConnecteur::STATUT_A_RECYCLER
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture(
            $fakeFactureChorus,
            $this->dateLimiteDePriseEnCharge,
            $this->statusCourant
        );

        self::assertFalse($canCreateFacture);
    }

    public function testCannotCreateFactureBecauseisFactureTravauxAndFactureHasBannedStatus(): void
    {
        $dateStatutCourant = date('Y-m-d', strtotime('+5 days'));

        $fakeFactureChorus = [
            'date_statut_courant' => $dateStatutCourant,
            'type_integration' => ChorusProImportUtilService::TYPE_INTEGRATION_CPP_TRAVAUX_CLE,
            'statut' => PortailFactureConnecteur::STATUT_A_RECYCLER
        ];

        $canCreateFacture = $this->checkIfCanCreateFacture->canCreateFacture(
            $fakeFactureChorus,
            $this->dateLimiteDePriseEnCharge,
            $this->statusCourant
        );

        self::assertFalse($canCreateFacture);
    }
}
