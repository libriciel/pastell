<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Connecteur;

use DocumentType;
use Pastell\Service\Connecteur\FluxConnecteurListService;
use Pastell\Service\Connecteur\FluxInheritanceService;
use PastellTestCase;

final class FluxConnecteurListServiceTest extends PastellTestCase
{
    private function getFluxConnecteurListService(): FluxConnecteurListService
    {
        return $this->getObjectInstancier()->getInstance(FluxConnecteurListService::class);
    }

    public function testGetConnecteurList(): void
    {
        $connecteur_list = $this->getFluxConnecteurListService()->getConnecteurList(1, 'test');
        $last_connecteur = end($connecteur_list);
        self::assertSame('test', $last_connecteur['connecteur_type']);
        self::assertSame(1, $last_connecteur[DocumentType::NUM_SAME_TYPE]);
        self::assertTrue($last_connecteur[DocumentType::CONNECTEUR_WITH_SAME_TYPE]);
        self::assertFalse($last_connecteur['inherited_flux']);
    }

    public function testConnecteurListWithAssociation(): void
    {
        $connecteur_list = $this->getFluxConnecteurListService()->getConnecteurList(1, 'actes-generique');
        self::assertSame('signature', $connecteur_list[0]['connecteur_type']);
        self::assertSame('Fake iParapheur', $connecteur_list[0]['connecteur_info']['libelle']);
    }

    public function testConnecteurListWithInheritance(): void
    {
        $this->getObjectInstancier()->getInstance(FluxInheritanceService::class)
            ->setInheritance(2, 'actes-generique');
        $connecteur_list = $this->getFluxConnecteurListService()->getConnecteurList(2, 'actes-generique');
        self::assertTrue($connecteur_list[0]['inherited_flux']);
        self::assertSame('Fake iParapheur', $connecteur_list[0]['connecteur_info']['libelle']);
    }

    public function testConnecteurListWithoutAssociation(): void
    {
        $connecteur_list = $this->getFluxConnecteurListService()->getConnecteurList(2, 'actes-generique');
        self::assertFalse($connecteur_list[0]['connecteur_info']);
    }

    public function testGetFluxList(): void
    {
        $flux_list = $this->getFluxConnecteurListService()->getFluxListWithConnecteur(1);
        self::assertSame(['actes-generique', 'mailsec', 'test'], array_keys($flux_list));
        self::assertArrayNotHasKey('formulaire', $flux_list['mailsec']);
        self::assertArrayNotHasKey('action', $flux_list['mailsec']);
        self::assertSame(1, $flux_list['mailsec']['nb_connector']);
        self::assertFalse($flux_list['mailsec']['affiche_hover']);
        self::assertSame('mailsec', $flux_list['mailsec']['famille_associe_affiche']);
    }

    public function testFluxListWithHover(): void
    {
        $flux_info = $this->getFluxConnecteurListService()->getFluxListWithConnecteur(1)['actes-generique'];
        self::assertSame(5, $flux_info['nb_connector']);
        self::assertTrue($flux_info['affiche_hover']);
        self::assertSame('signature, TdT, GED, Bordereau SEDA, SAE', $flux_info['hover']);
        self::assertSame('signature, TdT, GED, Bordereau SEDA, ...', $flux_info['famille_associe_affiche']);
    }

    public function testFluxListWithoutConnecteur(): void
    {
        self::assertSame([], $this->getFluxConnecteurListService()->getFluxListWithConnecteur(2));
    }

    public function testGetPossiblePackList(): void
    {
        $module_list = [
            'mailsec' => ['type' => 'Mails sécurisés', 'nom' => 'Mail sécurisé'],
            'unknown-flux' => ['type' => 'Autre', 'nom' => 'Inconnu'],
        ];
        self::assertSame(
            ['Mails sécurisés' => ['mailsec' => $module_list['mailsec']]],
            $this->getFluxConnecteurListService()->getPossiblePackList($module_list)
        );
    }
}
