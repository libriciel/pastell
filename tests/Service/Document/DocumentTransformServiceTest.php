<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Document;

use Pastell\Service\Document\DocumentPastellMetadataService;
use Pastell\Service\Document\DocumentTransformService;
use PastellTestCase;
use UnrecoverableException;

class DocumentTransformServiceTest extends PastellTestCase
{
    public function documentTransformService(): DocumentTransformService
    {
        return $this->getObjectInstancier()->getInstance(DocumentTransformService::class);
    }

    public function transformationDataProvider(): \Generator
    {
        yield 'Valid TransformationData' => [
            [
                'objet_document' => '{{ objet  ? objet :  "objet par défaut" }}',
                'envoi_depot' => 'true',
                'date_de_creation_document' => DocumentPastellMetadataService::PA_DOCUMENT_CREATION_DATE
            ],
            true ,
            ''
        ];
        yield 'Empty TransformationData' => [
            [],
            true,
            ''
        ];
        yield 'TransformationData with wrong elementID' => [
            [
                'objet document' => '{{ objet  ? objet :  "objet par défaut" }}',
            ],
            false,
            "L'identifiant de l'élément « objet document » ne respecte pas l'expression rationnelle : ^[a-z_][0-9a-z_]*$"
        ];
        yield 'TransformationData with wrong expressionTwig' => [
            [
                'objet_document' => '{{ objet  ? objet :  "objet par défaut" }',
            ],
            false,
            'Erreur de syntaxe sur le template twig ligne 1'
        ];
    }

    /**
     * @dataProvider transformationDataProvider
     * @throws UnrecoverableException
     */
    public function testValidateTransformationData(
        array $transformationData,
        bool $expectedResult,
        string $exceptionMessage
    ): void {
        if (! $expectedResult) {
            $this->expectException(UnrecoverableException::class);
            $this->expectExceptionMessage($exceptionMessage);
            $this->documentTransformService()->validateTransformationData($transformationData);
        }
        static::assertTrue($this->documentTransformService()->validateTransformationData($transformationData));
    }
}
