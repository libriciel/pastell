<?php

declare(strict_types=1);

class DaemonManagerTest extends PastellTestCase
{
    /** @var  DaemonManager */
    private DaemonManager $daemonManager;

    protected function setUp(): void
    {
        $this->daemonManager = $this->getObjectInstancier()->getInstance(DaemonManager::class);
    }

    public function testStatus()
    {
        $this->daemonManager->stop();
        $this->assertEquals(DaemonManager::IS_STOPPED, $this->daemonManager->status());
    }
}
