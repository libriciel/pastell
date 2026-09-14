<?php

declare(strict_types=1);

class CPPListeFactureTravauxTest extends ExtensionCppTestCase
{
    public static function getFactureTravauxProvider(): array
    {
        return [
            'FactureWithPisteAndRole' =>
                [
                    'MOA',
                    'Liste des factures de travaux ayant changé de statut entre le 2019-01-01 et le ' .
                    date('Y-m-d') . ' : {"listeFactures":[{"identifiantDestinataire":"00000000013456",' .
                    '"identifiantFournisseur":"00000000000727","dateDepot":"2019-07-11",' .
                    '"dateFactureTravaux":"2019-07-11","dateHeureEtatCourant":"2019-07-11T15:45:39.674+02:00",' .
                    '"designationDestinataire":"TAA074DESTINATAIRE","designationFournisseur":"TAA001DESTINATAIRE",' .
                    '"devise":"EUR","factureTelechargeeParDestinataire":true,"idDestinataire":"25784152",' .
                    '"idFactureTravaux":"4100169","montantAPayer":"10","montantHT":"10","montantTTC":"20",' .
                    '"numeroFactureTravaux":"20190711-1","statutFactureTravaux":"A_ASSOCIER_MOA",' .
                    '"typeDemandePaiement":"FACTURE_TRAVAUX","typeFactureTravaux":"PROJET_DECOMPTE_MENSUEL",' .
                    '"typeIdentifiantFournisseur":"SIRET"}]}',

                ],
            'FactureWithoutRole' =>
                [
                    '',
                    "Il faut sélectionner le rôle de l'utilisateur pour la récupération des factures de travaux",
                ],
        ];
    }


    /**
     * @dataProvider getFactureTravauxProvider
     * @throws Exception
     */
    public function testCPPListeFactureTravaux(string $userRole, string $expectedLastMessage): void
    {
        $cppWrapper = $this->getMockBuilder(CPPWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapper->expects($this->any())
            ->method('rechercheFactureTravaux')
            ->willReturn($this->getrechercheFactureTravaux());

        $cppWrapperFactory = $this->getMockBuilder(CPPWrapperFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $cppWrapperFactory->expects($this->any())->method('newInstance')->willReturn($cppWrapper);

        $this->getObjectInstancier()->setInstance(CPPWrapperFactory::class, $cppWrapperFactory);

        $id_ce_chorus = $this->createCppConnector('facture-cpp');
        $connecteurConfig = $this->getConnecteurFactory()->getConnecteurConfig($id_ce_chorus);
        $connecteurConfig->setData('user_role', $userRole);

        $this->triggerActionOnConnector($id_ce_chorus, 'list-facture-travaux');

        $this->assertLastMessage($expectedLastMessage);
    }
}
