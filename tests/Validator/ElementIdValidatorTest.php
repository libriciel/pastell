<?php

declare(strict_types=1);

namespace Pastell\Tests\Validator;

use Pastell\Validator\ElementIdValidator;
use PHPUnit\Framework\TestCase;
use UnrecoverableException;

class ElementIdValidatorTest extends TestCase
{
    public static function elementIdProvider(): \Generator
    {
        yield 'Valid ElementId' => ['objet_document', true , ''];
        yield 'Empty ElementId' => [
            '',
            false,
            "L'identifiant de l'élément «  » ne respecte pas l'expression rationnelle : ^[a-z_][0-9a-z_]*$"
        ];
        yield 'ElementId with capital' => [
            'Objet_document',
            false,
            "L'identifiant de l'élément « Objet_document » ne respecte pas l'expression rationnelle : ^[a-z_][0-9a-z_]*$"
        ];
        yield 'ElementId with 65 character' => [
            str_pad('', ElementIdValidator::ELEMENT_ID_MAX_LENGTH + 1, 'a'),
            false,
            "L'identifiant de l'élément « aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa » ne doit pas dépasser 64 caractères"
        ];
        yield 'ElementId with space' => [
            'objet document',
            false,
            "L'identifiant de l'élément « objet document » ne respecte pas l'expression rationnelle : ^[a-z_][0-9a-z_]*$"
        ];
    }

    /**
     * @dataProvider elementIdProvider
     * @throws UnrecoverableException
     */
    public function testValidateElementId(
        string $elementId,
        bool $expectedResult,
        string $exceptionMessage
    ): void {

        $elementIdValidator = new ElementIdValidator();

        if (! $expectedResult) {
            $this->expectException(UnrecoverableException::class);
            $this->expectExceptionMessage($exceptionMessage);
            $elementIdValidator->validate($elementId);
        }
        static::assertTrue($elementIdValidator->validate($elementId));
    }
}
