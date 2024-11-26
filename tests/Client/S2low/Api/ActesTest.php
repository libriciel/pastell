<?php

declare(strict_types=1);

namespace Pastell\Tests\Client\S2low\Api;

use GuzzleHttp\Psr7\Response;
use Pastell\Client\S2low\Api\Actes;
use Pastell\Client\S2low\Model\ActeListQuery;
use Pastell\Client\S2low\S2lowClient;
use Pastell\Client\S2low\S2lowClientException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;

class ActesTest extends TestCase
{
    private ClientInterface&MockObject $clientInterface;
    private Actes $actes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientInterface = $this->createMock(ClientInterface::class);
        $this->actes = new Actes(new S2lowClient($this->clientInterface));
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function testGetActesList(): void
    {
        $expected = <<<EOT
{
  "status_id": "13",
  "authority_id": "2",
  "offset": "0",
  "limit": "100",
  "transactions": [
    {
      "id": "7",
      "subject": "test",
      "number": "20240507_TEST01",
      "date": "2024-05-07",
      "nature_descr": "Actes individuels",
      "classification": "3.4",
      "type": "1"
    },
    {
      "id": "2",
      "subject": "sdfsdf",
      "number": "SDSD",
      "date": "2024-04-12",
      "nature_descr": "Actes individuels",
      "classification": "1.1",
      "type": "1"
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

        $acteListResponse = $this->actes->getActesList(new ActeListQuery());
        self::assertSame('13', $acteListResponse->statusId);
        self::assertSame('2', $acteListResponse->authorityId);
        self::assertSame('7', $acteListResponse->transactions[0]->id);
        self::assertSame('sdfsdf', $acteListResponse->transactions[1]->subject);
    }

    /**
     * @throws S2lowClientException
     * @throws ClientExceptionInterface
     */
    public function testGetFileList(): void
    {

        $expected = <<<EOT
[
  {
    "id": 19,
    "name": "034-000000000-20240507-20240507_TEST01-AI-1-1_0.xml",
    "posted_filename": "",
    "mimetype": "text\/xml",
    "size": 836,
    "sign": "",
    "code_pj": ""
  },
  {
    "id": 20,
    "name": "99_AI-034-000000000-20240507-20240507_TEST01-AI-1-1_1.pdf",
    "posted_filename": "PA-DOC-API-3.1.pdf",
    "mimetype": "application\/pdf",
    "size": 365631,
    "sign": "",
    "code_pj": "99_AI"
  }
]
EOT;

        $this->clientInterface
            ->expects(static::once())
            ->method('sendRequest')
            ->willReturn(
                new Response(200, [], $expected)
            );

        $fileList = $this->actes->getFileList('1');
        self::assertSame('19', $fileList[0]->id);
        self::assertSame('99_AI-034-000000000-20240507-20240507_TEST01-AI-1-1_1.pdf', $fileList[1]->name);
    }
}
