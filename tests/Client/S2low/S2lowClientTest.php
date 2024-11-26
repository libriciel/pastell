<?php

declare(strict_types=1);

namespace Client\S2low;

use Pastell\Client\S2low\Api\Actes;
use Pastell\Client\S2low\Api\Connexion;
use Pastell\Client\S2low\S2lowClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class S2lowClientTest extends TestCase
{
    private S2lowClient $sl2owClient;

    protected function setUp(): void
    {
        $clientInterface = $this->getMockBuilder(ClientInterface::class)->getMock();
        $this->sl2owClient = new S2lowClient($clientInterface);
    }
    public function getApiClassesProvider(): iterable
    {
        yield ['connexion', Connexion::class];
        yield ['actes', Actes::class];
    }

    /**
     * @dataProvider getApiClassesProvider
     */
    public function testShouldGetInstanceOfClass(string $apiName, string $classFqn): void
    {
        static::assertInstanceOf($classFqn, $this->sl2owClient->$apiName());
    }
}
