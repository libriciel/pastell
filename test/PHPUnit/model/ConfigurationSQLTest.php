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

        $this->configurationSQL->setConfiguration($key, $value);
        $result = $this->configurationSQL->getConfiguration($key);
        static::assertSame($value, $result);
    }

    public function testUpdateConfiguration(): void
    {
        $key = 'test_key';
        $initialValue = '5';
        $updatedValue = '10';

        $this->configurationSQL->setConfiguration($key, $initialValue);
        $this->configurationSQL->setConfiguration($key, $updatedValue);
        $result = $this->configurationSQL->getConfiguration($key);
        static::assertSame($updatedValue, $result);
    }

    public function testGetConfigurationReturnsNullIfNotExists(): void
    {
        $key = 'non_existing_key';
        $this->expectException(ConfigurationNotFoundException::class);
        $this->configurationSQL->getConfiguration($key);
    }

    public function testHasConfiguration(): void
    {
        $key = 'existing_key';
        $this->configurationSQL->setConfiguration($key, 'value');

        static::assertTrue($this->configurationSQL->hasConfiguration($key));
        static::assertFalse($this->configurationSQL->hasConfiguration('missing_key'));
    }
}
