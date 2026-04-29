<?php

declare(strict_types=1);

use Pastell\Exception\ConfigurationNotFoundException;

class ConfigurationSQLTest extends PastellTestCase
{
    private ConfigurationSQL $configurationSQL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configurationSQL =  $this->getObjectInstancier()->getInstance(ConfigurationSQL::class);
    }

    public function testSetAndGetConfiguration(): void
    {
        $key = 'test_key';
        $value = '5';

        $this->configurationSQL->setConfiguration($key, $value, ConfigurationSQL::NULL_ID_E);
        $result = $this->configurationSQL->getConfiguration($key, ConfigurationSQL::NULL_ID_E);
        static::assertSame($value, $result);
    }

    public function testUpdateConfiguration(): void
    {
        $key = 'test_key';
        $initialValue = '5';
        $updatedValue = '10';

        $this->configurationSQL->setConfiguration($key, $initialValue, ConfigurationSQL::NULL_ID_E);
        $this->configurationSQL->setConfiguration($key, $updatedValue, ConfigurationSQL::NULL_ID_E);
        $result = $this->configurationSQL->getConfiguration($key, ConfigurationSQL::NULL_ID_E);
        static::assertSame($updatedValue, $result);
    }

    public function testGetConfigurationReturnsNullIfNotExists(): void
    {
        $key = 'non_existing_key';
        $this->expectException(ConfigurationNotFoundException::class);
        $this->configurationSQL->getConfiguration($key, ConfigurationSQL::NULL_ID_E);
    }

    public function testHasConfiguration(): void
    {
        $key = 'existing_key';
        $this->configurationSQL->setConfiguration($key, 'value', ConfigurationSQL::NULL_ID_E);

        static::assertTrue($this->configurationSQL->hasConfiguration($key, ConfigurationSQL::NULL_ID_E));
        static::assertFalse($this->configurationSQL->hasConfiguration('missing_key', ConfigurationSQL::NULL_ID_E));
    }

    public function testWorkspaceThreshold(): void
    {
        $this->configurationSQL->setWorkspaceAlertThreshold(75);
        static::assertSame(75, $this->configurationSQL->getWorkspaceAlertThreshold());
    }

    public function testWorkspaceThresholdRejectsNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->configurationSQL->setWorkspaceAlertThreshold(-1);
    }

    public function testWorkspaceThresholdRejectsValueOver100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->configurationSQL->setWorkspaceAlertThreshold(101);
    }
}
