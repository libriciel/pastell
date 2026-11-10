<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Connecteur;

use Pastell\Service\Connecteur\ConnecteurAvailabilityService;
use PastellTestCase;

final class ConnecteurAvailabilityServiceTest extends PastellTestCase
{
    private function getConnecteurAvailabilityService(): ConnecteurAvailabilityService
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurAvailabilityService::class);
    }

    public function testGetListByType(): void
    {
        $result = $this->getConnecteurAvailabilityService()->getListByType(1, 1, 'mailsec');
        self::assertSame('Mail securise', $result[0]['libelle']);
    }

    public function testGetListByTypeNoRight(): void
    {
        self::assertEmpty($this->getConnecteurAvailabilityService()->getListByType(3, 1, 'mailsec'));
    }

    public function testGetListByTypeInherited(): void
    {
        $result = $this->getConnecteurAvailabilityService()->getListByType(1, 2, 'mailsec');
        self::assertSame('Mail securise', $result[0]['libelle']);
    }
}
