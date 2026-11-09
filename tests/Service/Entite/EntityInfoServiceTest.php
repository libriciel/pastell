<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Entite;

use EntiteSQL;
use Exception;
use NotFoundException;
use Pastell\Service\Entite\EntityHierarchyService;
use Pastell\Service\Entite\EntityInfoService;
use Pastell\Service\Entite\EntityType;
use PastellTestCase;

final class EntityInfoServiceTest extends PastellTestCase
{
    private function getEntityInfoService(): EntityInfoService
    {
        return $this->getObjectInstancier()->getInstance(EntityInfoService::class);
    }

    private function getEntiteSQL(): EntiteSQL
    {
        return $this->getObjectInstancier()->getInstance(EntiteSQL::class);
    }

    public function testGetCentreDeGestion(): void
    {
        self::assertNull($this->getEntityInfoService()->getCentreDeGestion(2));

        $cdg_id_e = $this->getEntiteSQL()->create('CDG', '', EntityType::CENTRE_DE_GESTION->value);
        $this->getEntiteSQL()->update(1, 'Bourg-en-Bresse', '000000000', EntityType::COLLECTIVITE->value, 0, $cdg_id_e);

        self::assertSame($cdg_id_e, $this->getEntityInfoService()->getCentreDeGestion(2));
    }

    public function testGetSiren(): void
    {
        self::assertSame('111111118', $this->getEntityInfoService()->getSiren(2));
    }

    public function testGetSirenInherited(): void
    {
        $id_e = $this->getEntiteSQL()->create('Child', '', EntityType::COLLECTIVITE->value, 2);
        $this->getObjectInstancier()->getInstance(EntityHierarchyService::class)->updateAncestors($id_e, 2);
        self::assertSame('000000000', $this->getEntityInfoService()->getSiren($id_e));
    }

    /**
     * @throws NotFoundException
     */
    public function testGetExtendedInfo(): void
    {
        $extended_info = $this->getEntityInfoService()->getExtendedInfo(1);

        self::assertSame('Bourg-en-Bresse', $extended_info['denomination']);
        self::assertSame([], $extended_info['cdg']);
        self::assertSame(['CCAS'], array_column($extended_info['filles'], 'denomination'));
        self::assertSame('Bourg-en-Bresse', $this->getEntityInfoService()->getExtendedInfo(2)['entite_mere']['denomination']);
    }

    /**
     * @throws NotFoundException
     */
    public function testExtendedInfoUnknownEntity(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("L'entité {id_e=42} n'existe pas");
        $this->getEntityInfoService()->getExtendedInfo(42);
    }

    /**
     * @throws Exception
     */
    public function testGetEntityFromData(): void
    {
        self::assertSame('Bourg-en-Bresse', $this->getEntityInfoService()->getEntityFromData(['id_e' => 1])['denomination']);
        self::assertSame(2, $this->getEntityInfoService()->getEntityFromData(['denomination' => 'CCAS'])['id_e']);
    }

    public function testFromDataUnknownId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("L'identifiant de l'entite n'existe pas : {id_e=42}");
        $this->getEntityInfoService()->getEntityFromData(['id_e' => 42]);
    }

    public function testFromDataUnknownDenomination(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("La dénomination de l'entité n'existe pas : {denomination=FizzBuzz}");
        $this->getEntityInfoService()->getEntityFromData(['denomination' => 'FizzBuzz']);
    }

    public function testFromDataDuplicateDenomination(): void
    {
        $this->getEntiteSQL()->create('CCAS');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Plusieurs entités portent le même nom, préférez utiliser son identifiant');
        $this->getEntityInfoService()->getEntityFromData(['denomination' => 'CCAS']);
    }

    public function testFromDataIsActiveOnly(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Aucun paramètre permettant la recherche de l'entité n'a été renseigné");
        $this->getEntityInfoService()->getEntityFromData(['is_active' => true]);
    }

    public function testFromDataEmpty(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Aucun paramètre permettant la recherche de l'entité n'a été renseigné");
        $this->getEntityInfoService()->getEntityFromData([]);
    }
}
