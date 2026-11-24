<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Connecteur;

use ConnecteurEntiteSQL;
use FluxEntiteHeritageSQL;
use FluxEntiteSQL;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Pastell\Service\Connecteur\FluxInheritanceService;
use PastellTestCase;
use UnrecoverableException;

final class FluxInheritanceServiceTest extends PastellTestCase
{
    private function getFluxInheritanceService(): FluxInheritanceService
    {
        return $this->getObjectInstancier()->getInstance(FluxInheritanceService::class);
    }

    private function getFluxEntiteSQL(): FluxEntiteSQL
    {
        return $this->getObjectInstancier()->getInstance(FluxEntiteSQL::class);
    }

    public function testGetAllWithSameType(): void
    {
        $all_flux = $this->getFluxInheritanceService()->getAllWithSameType(1);
        self::assertSame('Fake iParapheur', $all_flux['actes-generique']['signature'][0]['libelle']);
        self::assertFalse($all_flux['actes-generique']['inherited_flux']);
    }

    public function testGetAllWithoutFlux(): void
    {
        self::assertSame([], $this->getFluxInheritanceService()->getAllWithSameType(2));
    }

    public function testGetAllWithInheritance(): void
    {
        $this->getFluxInheritanceService()->setInheritance(2, 'actes-generique');
        $all_flux = $this->getFluxInheritanceService()->getAllWithSameType(2);
        self::assertSame('Fake iParapheur', $all_flux['actes-generique']['signature'][0]['libelle']);
        self::assertTrue($all_flux['actes-generique']['inherited_flux']);
    }

    public function testGetAllAllFlux(): void
    {
        $this->getFluxInheritanceService()->setInheritanceAllFlux(2);
        $all_flux = $this->getFluxInheritanceService()->getAllWithSameType(2);
        self::assertSame('Fake iParapheur', $all_flux['actes-generique']['signature'][0]['libelle']);
    }

    public function testInheritedWithoutParentFlux(): void
    {
        $this->getFluxEntiteSQL()->deleteConnecteur(1, 'mailsec', 'mailsec');
        $this->getFluxInheritanceService()->setInheritance(2, 'mailsec');
        $all_flux = $this->getFluxInheritanceService()->getAllWithSameType(2);
        self::assertSame(['inherited_flux' => true], $all_flux['mailsec']);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testGetAllTwoSameType(): void
    {
        $connector_info = $this->createConnector('test', 'test 2', 1);
        $this->getObjectInstancier()->getInstance(ConnecteurAssociationService::class)
            ->addConnecteurAssociation(1, (int)$connector_info['id_ce'], 'test', 0, 'test', 1);
        self::assertCount(2, $this->getFluxInheritanceService()->getAllWithSameType(1)['test']['test']);
    }

    public function testGetConnecteurId(): void
    {
        self::assertSame(1, $this->getFluxInheritanceService()->getConnecteurId(1, 'actes-generique', 'signature'));
        self::assertFalse($this->getFluxInheritanceService()->getConnecteurId(2, 'actes-generique', 'signature'));
    }

    public function testConnecteurIdInherited(): void
    {
        $this->getFluxInheritanceService()->setInheritance(2, 'actes-generique');
        self::assertSame(1, $this->getFluxInheritanceService()->getConnecteurId(2, 'actes-generique', 'signature'));
    }

    public function testConnecteurIdAllFlux(): void
    {
        $this->getFluxInheritanceService()->setInheritanceAllFlux(2);
        self::assertSame(1, $this->getFluxInheritanceService()->getConnecteurId(2, 'actes-generique', 'signature'));
    }

    public function testInheritanceHidesOwnConnecteur(): void
    {
        $this->getFluxEntiteSQL()->deleteConnecteur(1, 'mailsec', 'mailsec');
        $id_ce = (int)$this->getObjectInstancier()->getInstance(ConnecteurEntiteSQL::class)
            ->addConnecteur(2, 'mailsec', 'mailsec', 'connecteur mailsec de test', 0);
        $this->getFluxEntiteSQL()->addConnecteur(2, 'mailsec', 'mailsec', $id_ce);
        self::assertSame($id_ce, $this->getFluxInheritanceService()->getConnecteurId(2, 'mailsec', 'mailsec'));

        $this->getFluxInheritanceService()->setInheritance(2, 'mailsec');
        self::assertFalse($this->getFluxInheritanceService()->getConnecteurId(2, 'mailsec', 'mailsec'));
    }

    public function testSetInheritance(): void
    {
        $this->getFluxInheritanceService()->setInheritance(2, 'actes-generique');
        $this->getFluxInheritanceService()->setInheritance(2, 'actes-generique');
        self::assertSame(
            ['actes-generique'],
            $this->getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class)->getInheritance(2)
        );
    }

    public function testSetInheritanceOnTopEntity(): void
    {
        $this->getFluxInheritanceService()->setInheritance(1, 'actes-generique');
        self::assertSame(
            [],
            $this->getObjectInstancier()->getInstance(FluxEntiteHeritageSQL::class)->getInheritance(1)
        );
    }

    public function testToggleInheritance(): void
    {
        $this->getFluxInheritanceService()->toggleInheritance(2, 'actes-generique');
        self::assertSame(1, $this->getFluxInheritanceService()->getConnecteurId(2, 'actes-generique', 'signature'));
        $this->getFluxInheritanceService()->toggleInheritance(2, 'actes-generique');
        self::assertFalse($this->getFluxInheritanceService()->getConnecteurId(2, 'actes-generique', 'signature'));
    }
}
