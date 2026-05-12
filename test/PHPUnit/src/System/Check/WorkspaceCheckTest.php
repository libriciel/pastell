<?php

declare(strict_types=1);

use Pastell\System\Check\WorkspaceCheck;
use Pastell\System\HealthCheckItem;

class WorkspaceCheckTest extends PastellTestCase
{
    private function getFreeSpaceMock(): FreeSpace
    {
        $freeSpace = $this->createMock(FreeSpace::class);
        $freeSpace->method('getFreeSpace')->willReturn([
            'disk_use_space' => '5.00 GB',
            'disk_total_space' => '10.00 GB',
            'disk_use_percent' => 75.0 . ' %',
        ]);
        $freeSpace->method('getUsagePercent')->willReturn(75.0);
        return $freeSpace;
    }

    private function getTauxOccupation(int $threshold): HealthCheckItem
    {
        $this->getObjectInstancier()->setInstance(FreeSpace::class, $this->getFreeSpaceMock());
        $this->getObjectInstancier()->getInstance(ConfigurationSQL::class)->setWorkspaceAlertThreshold($threshold);
        return $this->getObjectInstancier()->getInstance(WorkspaceCheck::class)->check()[3];
    }

    public function testSuccessUnderThreshold(): void
    {
        static::assertTrue($this->getTauxOccupation(80)->isSuccess());
    }

    public function testFailureOverThreshold(): void
    {
        static::assertFalse($this->getTauxOccupation(70)->isSuccess());
    }

    public function testFailureAtThreshold(): void
    {
        static::assertFalse($this->getTauxOccupation(75)->isSuccess());
    }
}
