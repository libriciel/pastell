<?php

namespace Pastell\Tests\Client\Crypto;

use Pastell\Client\Crypto\Api\Cades;
use Pastell\Client\Crypto\Api\Pades;
use Pastell\Client\Crypto\Api\Version;
use Pastell\Client\Crypto\Api\Xades;
use Pastell\Client\Crypto\CryptoClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class CryptoClientTest extends TestCase
{
    /**
     * @var CryptoClient
     */
    private $cryptoClient;

    protected function setUp(): void
    {
        $clientInterface = $this->getMockBuilder(ClientInterface::class)->getMock();
        $this->cryptoClient = new CryptoClient($clientInterface);
    }

    public static function getApiClassesProvider(): iterable
    {
        yield ['cades', Cades::class];
        yield ['xades', Xades::class];
        yield ['pades', Pades::class];
        yield ['version', Version::class];
    }

    /**
     * @dataProvider getApiClassesProvider
     */
    public function testShouldGetInstanceOfClass(string $apiName, string $classFqn): void
    {
        $this->assertInstanceOf($classFqn, $this->cryptoClient->$apiName());
    }
}
