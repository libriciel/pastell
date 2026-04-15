<?php

class TypeDossierFormulaireElementManagerTest extends \PHPUnit\Framework\TestCase
{
    public function elementIdProvider()
    {
        return [
            [
                'matricule_agent',
                true,
                ""
            ],
            [
                'MATRICULE_AGENT',
                false,
                "L'identifiant de l'élément ne peut comporter que des chiffres, des lettres minuscules et le caractère _"
            ],
            [
                '1_matricule_agent',
                false,
                "L'identifiant de l'élément ne doit pas commencer par un chiffre"
            ],
            [
                str_pad(
                    "",
                    TypeDossierFormulaireElementManager::ELEMENT_ID_MAX_LENGTH + 1,
                    "a"
                ),
                false,
                "La longueur de l'identifiant ne peut dépasser 64 caractères"
            ],
            [
                str_pad(
                    "",
                    TypeDossierFormulaireElementManager::ELEMENT_ID_MAX_LENGTH,
                    "a"
                ),
                true,
                ""
            ],
            [
                '',
                false,
                "L'identifiant ne peut être vide"
            ]
        ];
    }

    /**
     * @dataProvider elementIdProvider
     *
     * @param $element_id
     * @param $expected_result
     * @param $exception_message
     * @throws Exception
     */
    public function testElementId($element_id, $expected_result, $exception_message)
    {
        $typeDossierFormulaireElementManager = new TypeDossierFormulaireElementManager();
        $typeDossierFormulaireElement = new TypeDossierFormulaireElementProperties();
        if (! $expected_result) {
            $this->expectException(TypeDossierException::class);
            $this->expectExceptionMessage(
                $exception_message
            );
        }
        $this->assertTrue($typeDossierFormulaireElementManager->edition(
            $typeDossierFormulaireElement,
            new Recuperateur([
                'element_id' => $element_id,
                'type' => TypeDossierFormulaireElementManager::TYPE_TEXT
            ])
        ));
    }

    public function testBadType()
    {
        $typeDossierFormulaireElementManager = new TypeDossierFormulaireElementManager();
        $typeDossierFormulaireElement = new TypeDossierFormulaireElementProperties();

        $this->expectException(TypeDossierException::class);
        $this->expectExceptionMessage(
            "Le type n'existe pas"
        );

        $this->assertTrue($typeDossierFormulaireElementManager->edition(
            $typeDossierFormulaireElement,
            new Recuperateur([
                'element_id' => "foo",
                'type' => 'bar'
            ])
        ));
    }

    public function testGetElementFromArray()
    {
        $typeDossierFormulaireElementManager = new TypeDossierFormulaireElementManager();
        $typeDossierFormulaireElement =
            $typeDossierFormulaireElementManager->getElementFromArray(['element_id' => 'foo']);
        $this->assertEquals("foo", $typeDossierFormulaireElement->element_id);
        $this->assertEquals("", $typeDossierFormulaireElement->type);
    }

    /**
     * @throws TypeDossierException
     */
    public function testEditionElementWithoutLibelle()
    {
        $typeDossierFormulaireElement = new TypeDossierFormulaireElementProperties();
        $typeDossierFormulaireElementManager = new TypeDossierFormulaireElementManager();
        $typeDossierFormulaireElementManager->edition($typeDossierFormulaireElement, new Recuperateur([
            'element_id' => 'foo',
            'type' => TypeDossierFormulaireElementManager::TYPE_TEXT
        ]));
        $this->assertEquals("foo", $typeDossierFormulaireElement->element_id);
        $this->assertEquals("foo", $typeDossierFormulaireElement->name);
    }

    public function testEditionElementWithDefault(): void
    {
        $typeDossierFormulaireElement = new TypeDossierFormulaireElementProperties();
        $typeDossierFormulaireElementManager = new TypeDossierFormulaireElementManager();
        $typeDossierFormulaireElementManager->edition($typeDossierFormulaireElement, new Recuperateur([
            'element_id' => 'id_1',
            'name' => 'nomtest',
            'type' => 'text',
            'default_value' => 'Mon nom',
        ]));
        $this->assertEquals('Mon nom', $typeDossierFormulaireElement->default_value);
        $typeDossierFormulaireElementManager->edition($typeDossierFormulaireElement, new Recuperateur([
            'element_id' => 'id_2',
            'name' => 'checkboxtest',
            'type' => 'checkbox',
            'default_value' => 'on',
        ]));
        $this->assertEquals('on', $typeDossierFormulaireElement->default_value);
        $typeDossierFormulaireElementManager->edition($typeDossierFormulaireElement, new Recuperateur([
            'element_id' => 'id_3',
            'name' => 'selectiontest',
            'type' => 'select',
            'default_value' => '1',
        ]));
        $this->assertEquals('1', $typeDossierFormulaireElement->default_value);
    }
}
