<?php

class TypeDossierSignatureEtapeTest extends PastellTestCase
{
    /**
     * @return TypeDossierProperties
     */
    private function getDefaultTypeDossierProperties(): TypeDossierProperties
    {
        $typeDossierData = new TypeDossierProperties();
        $typeDossierData->etape[] = new TypeDossierEtapeProperties();
        $typeDossierData->etape[0]->type = 'signature';
        $typeDossierData->etape[0]->specific_type_info['has_date_limite'] = '';
        $typeDossierData->etape[0]->specific_type_info['has_primo_signature'] = '';
        $typeDossierData->etape[0]->specific_type_info['libelle_parapheur'] = 'objet';
        $typeDossierData->etape[0]->specific_type_info['document_a_signer'] = 'document';
        $typeDossierData->etape[0]->specific_type_info['annexe'] = 'annexe';

        return $typeDossierData;
    }

    public function testAutreDocumentASignerIsMapped(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['autre_document_a_signer'] = 'autre_document_a_signer';

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        static::assertEquals(
            'autre_document_a_signer',
            $result['action']['send-iparapheur']['connecteur-type-mapping']['autre_document_a_signer']
        );
    }

    public function testAutreDocumentASignerIsNotMappedWhenEmpty(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        static::assertArrayNotHasKey(
            'autre_document_a_signer',
            $result['action']['send-iparapheur']['connecteur-type-mapping']
        );
    }

    public function testHasDateLimite()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['has_date_limite'] = 'on';

        $result = $typeDossierTranslator->getDefinition($typeDossierData);
        $this->assertEquals('Utiliser une date limite', $result['formulaire']['iparapheur']['has_date_limite']['name']);
    }

    public function testHasNoDateLimite()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();

        $result = $typeDossierTranslator->getDefinition($typeDossierData);
        $this->assertArrayNotHasKey('has_date_limite', $result['formulaire']['iparapheur']);
    }

    public function testHasPrimoSignature(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['has_primo_signature'] = 'on';

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        static::assertEquals('Primo-signature détachée', $result['formulaire']['iparapheur']['primo_signature_detachee']['name']);
        static::assertEquals('primo_signature_detachee', $result['action']['send-iparapheur']['connecteur-type-mapping']['primo_signature_detachee']);
    }

    public function testHasNoPrimoSignature(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();

        $result = $typeDossierTranslator->getDefinition($typeDossierData);
        static::assertArrayNotHasKey('primo_signature_detachee', $result['formulaire']['iparapheur']);
        static::assertArrayNotHasKey('primo_signature_detachee', $result['action']['send-iparapheur']['connecteur-type-mapping']);
    }

    public function testGetSpecific()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->etape_with_same_type_exists = true;

        $result = $typeDossierTranslator->getDefinition($typeDossierData);
        $this->assertEquals('objet', $result['action']['send-iparapheur_1']['connecteur-type-mapping']['objet']);
        $this->assertEquals(1, $result['page-condition']['Signature #1']['has_signature_1']);
    }

    public function testContinueFileProgressAfterRefusal()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['continue_after_refusal'] = true;

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        $this->assertContains(
            'rejet-iparapheur',
            $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION]['rule']['last-action']
        );

        $this->assertArrayNotHasKey(
            'action-automatique',
            $result[DocumentType::ACTION]['rejet-iparapheur']
        );
    }

    public function testAutomaticallyContinueFileProgressAfterRefusal()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['continue_after_refusal'] = true;
        $typeDossierData->etape[0]->automatique = true;

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        $this->assertContains(
            'rejet-iparapheur',
            $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION]['rule']['last-action']
        );

        $this->assertArrayHasKey(
            'action-automatique',
            $result[DocumentType::ACTION]['rejet-iparapheur']
        );
    }
}
