<?php

declare(strict_types=1);

namespace Pastell\Tests\Client\S2low\Api;

use GuzzleHttp\Psr7\Response;
use Pastell\Client\S2low\Api\Pes;
use Pastell\Client\S2low\Model\PesAllerListQuery;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

class PesTest extends TestCase
{
    private ClientInterface&MockObject $clientInterface;
    private Pes $pes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientInterface = $this->createMock(ClientInterface::class);
        $this->pes = new Pes(new S2lowClient($this->clientInterface));
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function testGetPesAllerList(): void
    {
        $expected = <<<EOT
{
  "status_id": "8",
  "authority_id": "2",
  "offset": "0",
  "limit": "10",
  "transactions": [
    {
      "id": "5"
    }
  ]
}
EOT;
        $this->clientInterface
            ->expects(static::once())
            ->method('sendRequest')
            ->willReturn(
                new Response(200, [], $expected)
            );

        $pesListResponse = $this->pes->getPesAllerList(new PesAllerListQuery());
        self::assertSame('8', $pesListResponse->statusId);
        self::assertSame('2', $pesListResponse->authorityId);
        self::assertSame('5', $pesListResponse->transactions[0]['id']);
    }
}
