<?php

namespace Pastell\Tests\Service\TypeDossier;

use Pastell\Service\TypeDossier\TypeDossierEditionService;
use Pastell\Service\TypeDossier\TypeDossierManager;
use PastellTestCase;
use TypeDossierException;
use TypeDossierProperties;
use TypeDossierTranslator;

class TypeDossierEditionServiceTest extends PastellTestCase
{
    private function getTypeDossierEditionService()
    {
        return $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
    }

    public static function typeDossierIdProvider(): \Generator
    {
        yield 'empty' => ['', 'Aucun identifiant de type de dossier fourni'];
        yield 'exists' => ['actes-generique', 'Le type de dossier actes-generique existe déjà sur ce Pastell'];
        yield 'pastell-' => [
            'pastell-test',
            \sprintf(
                "L'identifiant du type de dossier ne doit pas commencer par : %s ou %s",
                TypeDossierEditionService::TYPE_DOSSIER_ID_PASTELL,
                TypeDossierEditionService::TYPE_DOSSIER_ID_LS,
            ),
            ];
        yield 'ls-' => [
            'ls-test',
            \sprintf(
                "L'identifiant du type de dossier ne doit pas commencer par : %s ou %s",
                TypeDossierEditionService::TYPE_DOSSIER_ID_PASTELL,
                TypeDossierEditionService::TYPE_DOSSIER_ID_LS,
            ),
        ];
        yield 'regex' => [
            'studio_',
            \sprintf(
                "L'identifiant du type de dossier « studio_ » ne respecte pas l'expression rationnelle : %s",
                TypeDossierEditionService::TYPE_DOSSIER_ID_REGEXP
            ),
        ];
        yield 'maxLength' => [
            '123456789-123456789-123456789-123',
            \sprintf(
                "L'identifiant du type de dossier « %s » ne respecte pas l'expression rationnelle : %s",
                '123456789-123456789-123456789-123',
                TypeDossierEditionService::TYPE_DOSSIER_ID_REGEXP
            ),
        ];
    }

    /**
     * @dataProvider typeDossierIdProvider
     */
    public function testCheckTypeDossierId(string $type_dossier_id, string $exception_message): void
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = $type_dossier_id;
        $typeDossierEditionService = $this->getTypeDossierEditionService();

        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage($exception_message);
        $typeDossierEditionService->create($typeDossierProperties);
    }

    /**
     * @throws TypeDossierException
     */
    public function testRenameTypeDossierIdFailed()
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = 'test-42';
        $typeDossierEditionService = $this->getTypeDossierEditionService();
        $id_t = $typeDossierEditionService->create($typeDossierProperties);

        $typeDossierProperties->id_type_dossier = 'test-43';
        $typeDossierEditionService->edit($id_t, $typeDossierProperties);

        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage("L'emplacement du type de dossier « test-43 » est déjà utilisé.");

        $this->getTypeDossierEditionService()->renameTypeDossierId("test-42", "test-43");
    }

    public function testEditLibelleInfo()
    {
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = "arrete-rh";
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        $id_t = $typeDossierEditionService->create($typeDossierProperties);

        $this->getTypeDossierEditionService()->editLibelleInfo(
            $id_t,
            "arrete-rh",
            "Flux CD 99",
            "Ceci est un flux de test",
            "Information"
        );
        $this->assertEquals(
            [
                'id_type_dossier' => 'arrete-rh',
                'nom' => 'arrete-rh',
                'type' => 'Flux CD 99',
                'description' => 'Ceci est un flux de test',
                'nom_onglet' => 'Information',
                'formulaireElement' =>
                    [],
                'etape' =>
                    [],
                'restriction_pack' => '',
                'affiche_one' => false,
            ],
            $this->getObjectInstancier()->getInstance(TypeDossierManager::class)->getRawData($id_t)
        );
    }

    public function testAfficheOne(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierProperties = new TypeDossierProperties();
        $typeDossierProperties->id_type_dossier = 'test-42';
        $typeDossierProperties->affiche_one = true;
        $typeDossierEditionService = $this->getObjectInstancier()->getInstance(TypeDossierEditionService::class);
        $typeDossierEditionService->create($typeDossierProperties);
        $result = $typeDossierTranslator->getDefinition($typeDossierProperties);
        self::assertTrue($result['affiche_one']);
    }
}
