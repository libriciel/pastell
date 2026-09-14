<?php

declare(strict_types=1);

namespace Pastell\Tests\Validator;

use EntiteSQL;
use Pastell\Validator\EntityValidator;
use PastellTestCase;
use Siren;
use UnrecoverableException;

class EntityValidatorTest extends PastellTestCase
{
    private function entityValidator(): EntityValidator
    {
        return new EntityValidator($this->getObjectInstancier()->getInstance(EntiteSQL::class), new Siren());
    }

    /**
     * @throws UnrecoverableException
     */
    public function testValidate(): void
    {
        static::assertTrue($this->entityValidator()->validate('name', '', EntiteSQL::TYPE_COLLECTIVITE, 0, 0));
    }

    public static function validationProvider(): \Generator
    {
        yield 'empty name' => ['', '', '', 'Le nom (denomination) est obligatoire'];
        yield 'wrong siren' => ['name', '1234', '', 'Le siren « 1234 » ne semble pas valide'];
        yield 'wrong type' => [
            'name',
            '',
            '',
            "Le type d'entité doit être renseigné. Les valeurs possibles sont collectivite ou centre_de_gestion."
        ];
    }

    /**
     * @dataProvider validationProvider
     */
    public function testValidateErrors(
        string $name,
        string $siren,
        string $type,
        string $expectedMessage
    ): void {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage($expectedMessage);
        $this->entityValidator()->validate($name, $siren, $type, 0, 0);
    }

    public function testValidateDeactivated(): void
    {
        $this->getObjectInstancier()->getInstance(EntiteSQL::class)->setActive(self::ID_E_COL, false);
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            sprintf("L'entité id_e=%s est désactivée, il n'est pas possible de créer une entité fille", self::ID_E_COL)
        );
        $this->entityValidator()->validate('name', '', EntiteSQL::TYPE_COLLECTIVITE, self::ID_E_COL, 0);
    }

    public function testValidateCDG(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            sprintf("L'entité %s ne peut pas être utilisée comme centre de gestion", self::ID_E_COL)
        );
        $this->entityValidator()->validate('name', '', EntiteSQL::TYPE_COLLECTIVITE, 0, self::ID_E_COL);
    }

    /**
     * @throws UnrecoverableException
     */
    public function testCheckEntiteMereOK(): void
    {
        static::assertTrue($this->entityValidator()->checkEntiteMere(10, 10));
    }

    public function testCheckEntiteMereKO(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("L'entité mère id_e=10 ne peut pas être remplacée par une autre entité id_e=11");
        $this->entityValidator()->checkEntiteMere(10, 11);
    }
}
