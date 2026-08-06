<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class DocumentTypeTest extends PHPUnit\Framework\TestCase
{
    private function getDocumentTypeByFilename($defintion_filename)
    {
        $ymlLoader = new YMLLoader(new MemoryCacheNone());
        $document_type_array = $ymlLoader->getArray($defintion_filename);
        return new DocumentType("test", $document_type_array);
    }

    private function getDocumentType()
    {
        return $this->getDocumentTypeByFilename(__DIR__ . "/../fixtures/definition-exemple.yml");
    }

    private function getEmptyDocumentType()
    {
        return $this->getDocumentTypeByFilename(__DIR__ . "/../fixtures/definition-empty.yml");
    }

    public function testExists()
    {
        $documentType = new DocumentType("test", []);
        $this->assertFalse($documentType->exists());
    }

    public function testGetInfo()
    {
        $documentType = $this->getDocumentType();
        $this->assertEquals("Test", $documentType->getName());
        $this->assertEquals("flux de test, ne pas utiliser.", $documentType->getDescription());
        $this->assertEquals("Flux de test", $documentType->getType());
    }

    public function testGetListRestrictionPack()
    {
        $documentType = $this->getDocumentType();
        $this->assertEquals(['pack_marche'], $documentType->getListRestrictionPack());
    }

    public function testGetConnecteur()
    {
        $documentType = $this->getDocumentType();
        $this->assertEquals(['SAE'], $documentType->getConnecteur());
    }

    public function testGetFormulaire()
    {
        $this->assertInstanceOf("Formulaire", $this->getDocumentType()->getFormulaire());
    }

    public function testGetPageCondition()
    {
        $page_condition = $this->getDocumentType()->getPageCondition();
        $this->assertEquals(1, $page_condition['onglet2']['test3']);
    }

    public function testAfficheOneTab()
    {
        $this->assertFalse($this->getDocumentType()->isAfficheOneTab());
    }

    public function testGetAction()
    {
        $this->assertInstanceOf("Action", $this->getDocumentType()->getAction());
    }

    public function testGetTabAction()
    {
        $tab_action = $this->getDocumentType()->getTabAction();
        $this->assertArrayHasKey('creation', $tab_action);
    }

    public function testGetChampsAffiches()
    {
        $champs_affiche = $this->getDocumentType()->getChampsAffiches();
        $this->AssertEquals("test4", $champs_affiche['test4']);
    }

    public function testGetChampsRechercheAvancee()
    {
        $champs_recherche_avance = $this->getDocumentType()->getChampsRechercheAvancee();
        $this->AssertEquals("nom", $champs_recherche_avance[0]);
    }

    public function testGetChampsRechercheAvanceeByIndex()
    {
        $ymlLoader = new YMLLoader(new MemoryCacheNone());
        $document_type_array = $ymlLoader->getArray(__DIR__ . "/../fixtures/definition-exemple.yml");
        unset($document_type_array['champs-recherche-avancee']);
        $documentType = new DocumentType("test", $document_type_array);
        $champs_recherche_avance = $documentType->getChampsRechercheAvancee();
        $this->AssertEquals("type", $champs_recherche_avance[0]);
    }

    public function testInfoEmpty()
    {
        $documentType = $this->getEmptyDocumentType();
        $this->assertEquals("test", $documentType->getName());
        $this->assertFalse($documentType->getDescription());
        $this->assertEmpty($documentType->getListRestrictionPack());
        $this->assertEquals(DocumentType::TYPE_FLUX_DEFAULT, $documentType->getType());
        $this->assertEmpty($documentType->getConnecteur());
        $this->assertEmpty($documentType->getPageCondition());
        $this->assertEmpty($documentType->getTabAction());
        $this->assertInstanceOf("Action", $documentType->getAction());
        $champsAffiches = $documentType->getChampsAffiches();
        $this->assertEquals('Titre', $champsAffiches['titre']);
        $champsRecherche = $documentType->getChampsRechercheAvancee();
        $this->assertEquals('type', $champsRecherche[0]);
    }

    public function testGetDroitEmpty()
    {
        $droit_list = $this->getEmptyDocumentType()->getListDroit();
        $this->assertEquals(
            [
                DroitService::getDroitFor('test', DroitType::LECTURE),
                DroitService::getDroitFor('test', DroitType::EDITION),
            ],
            $droit_list
        );
    }

    public function testGetDroit()
    {
        $droit_list = $this->getDocumentType()->getListDroit();
        $this->assertCount(3, $droit_list);
        $this->assertEquals("test:teletransmettre", $droit_list[2]);
    }

    public function testGetConnecteurAllInfo()
    {
        $documentType = $this->getDocumentTypeByFilename(__DIR__ . "/../../../module/test/definition.yml");

        $connecteur_list = $documentType->getConnecteurAllInfo();

        $this->assertEquals(
            [
                0 =>
                     [
                        DocumentType::CONNECTEUR_ID => 'SAE',
                        DocumentType::NUM_SAME_TYPE => 0,
                        DocumentType::CONNECTEUR_WITH_SAME_TYPE => false
                    ],
                1 =>
                     [
                        DocumentType::CONNECTEUR_ID => 'test',
                        DocumentType::NUM_SAME_TYPE => 0,
                        DocumentType::CONNECTEUR_WITH_SAME_TYPE => true
                    ],
                2 =>
                     [
                        DocumentType::CONNECTEUR_ID => 'test',
                        DocumentType::NUM_SAME_TYPE => 1,
                        DocumentType::CONNECTEUR_WITH_SAME_TYPE => true
                    ],
             ],
            $connecteur_list
        );
    }
}
