<?php

class TypeDossierSAEEtapeTest extends PastellTestCase
{
    /**
     * @return TypeDossierProperties
     */
    private function getDefaultTypeDossierProperties(): TypeDossierProperties
    {
        $typeDossierData = new TypeDossierProperties();
        $typeDossierData->etape[] = new TypeDossierEtapeProperties();
        $typeDossierData->etape[0]->type = 'sae';

        return $typeDossierData;
    }

    public function testSetSpecificInfo()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $result = $typeDossierTranslator->getDefinition($typeDossierData);
        $this->assertArrayNotHasKey('Configuration SAE', $result['page-condition']);
    }

    public function testHasConfigurationSAE()
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['sae_has_metadata_in_json'] = 'On';
        $result = $typeDossierTranslator->getDefinition($typeDossierData);
        $this->assertArrayHasKey('Configuration SAE', $result['page-condition']);
    }

    public function testContinueFileProgressAfterRefusal(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['continue_after_refusal'] = true;

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        static::assertContains(
            'rejet-sae',
            $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION]['rule']['last-action']
        );

        static::assertArrayNotHasKey(
            'action-automatique',
            $result[DocumentType::ACTION]['rejet-sae']
        );
    }

    public function testAutomaticallyContinueFileProgressAfterRefusal(): void
    {
        $typeDossierTranslator = $this->getObjectInstancier()->getInstance(TypeDossierTranslator::class);
        $typeDossierData = $this->getDefaultTypeDossierProperties();
        $typeDossierData->etape[0]->specific_type_info['continue_after_refusal'] = true;
        $typeDossierData->etape[0]->automatique = true;

        $result = $typeDossierTranslator->getDefinition($typeDossierData);

        static::assertContains(
            'rejet-sae',
            $result[DocumentType::ACTION][TypeDossierTranslator::ORIENTATION]['rule']['last-action']
        );

        static::assertArrayHasKey(
            'action-automatique',
            $result[DocumentType::ACTION]['rejet-sae']
        );
    }
}
