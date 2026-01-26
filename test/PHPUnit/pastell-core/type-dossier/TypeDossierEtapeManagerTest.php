<?php

use Pastell\Service\Pack\PackService;

class TypeDossierEtapeManagerTest extends PastellTestCase
{
    private function getTypeDossierEtapeManager()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);
    }

    public function testGetAllType()
    {
        $all_type = $this->getTypeDossierEtapeManager()->getAllType();
        $this->assertContains('Dépôt (GED, FTP, ...)', $all_type);
        $this->assertArrayNotHasKey("type-dossier-starter-kit.yml", $all_type);
    }

    public function testGetLibelle()
    {
        $this->assertEquals(
            'Dépôt (GED, FTP, ...)',
            $this->getTypeDossierEtapeManager()->getLibelle('depot')
        );
    }

    public function testSetSpecificEtapeProperties()
    {
        $etape = new TypeDossierEtapeProperties();
        $etape->type = 'sae';
        $result = ['formulaire' => ['Configuration SAE' => ['element1' => []]], 'action' => []];
        $result = $this->getTypeDossierEtapeManager()->setSpecificData($etape, $result);
        $this->assertEquals([
            'formulaire' => [],
            'action' => [
                'supression' => [
                    'rule' => [
                        'last-action' => [
                            'rejet-sae',
                            'erreur-envoie-sae'
                        ]
                    ]
                ]
            ]
        ], $result);
    }

    public function testSpecificEtapeWhenHasExtensions()
    {
        $redisWrapper = $this->getObjectInstancier()->getInstance(MemoryCache::class);
        $redisWrapper->flushAll();

        $extensionsLoader = $this->getObjectInstancier()->getInstance(ExtensionLoader::class);
        $extensionsLoader->loadExtension([__DIR__ . "/fixtures/extension_test/"]);

        $this->assertEquals(
            'HAL 9000',
            $this->getTypeDossierEtapeManager()->getLibelle('hal-9000')
        );

        $redisWrapper = $this->getObjectInstancier()->getInstance(MemoryCache::class);
        $redisWrapper->flushAll();
    }

    public function testEtapeDoesNotExists()
    {
        $etape = new TypeDossierEtapeProperties();
        $etape->type = 'foo';
        $initial_result = ['formulaire' => [], 'action' => []];
        $result = $this->getTypeDossierEtapeManager()->setSpecificData($etape, $initial_result);
        $this->assertEquals($initial_result, $result);
    }

    public function testTwoSameTypeSteps()
    {
        $typeDossierEtapeManager = $this->getTypeDossierEtapeManager();
        $typeDossierEtapeProperties = new TypeDossierEtapeProperties();
        $typeDossierEtapeProperties->num_etape = '1';
        $typeDossierEtapeProperties->type = 'mailsec';
        $typeDossierEtapeProperties->num_etape_same_type = 1;
        $typeDossierEtapeProperties->etape_with_same_type_exists = true;
        $result = $typeDossierEtapeManager->getFormulaireForEtape($typeDossierEtapeProperties);
        static::assertSame('password_2', $result['Mail sécurisé #2']['password2_2']['is_equal']);
    }

    public function testGetAllRestricted(): void
    {
        $objectInstancier = $this->getObjectInstancier();
        $extensionsMock = $this->createMock(Extensions::class);
        $extensionsMock->method('getAllTypeDossier')
            ->willReturn([
                'depot' => '/var/www/pastell/test/PHPUnit/pastell-core/type-dossier/fixtures/restriction_pack_test'
            ]);
        $extensionsMock->method('getTypeDossierPath')
            ->willReturn(
                '/var/www/pastell/test/PHPUnit/pastell-core/type-dossier/fixtures/restriction_pack_test'
            );
        $typeDossierEtapeManager = new TypeDossierEtapeManager(
            $objectInstancier->getInstance(YMLLoader::class),
            $extensionsMock,
            $objectInstancier->getInstance(PackService::class)
        );

        $this->setListPack(['suppl_test' => false]);
        $result = $typeDossierEtapeManager->getAllType();
        static::assertEmpty($result);
        $this->setListPack(['suppl_test' => true]);
        $result = $typeDossierEtapeManager->getAllType();
        static::assertSame(['restriction_pack_test' => 'Test restriction pack'], $result);
    }
}
