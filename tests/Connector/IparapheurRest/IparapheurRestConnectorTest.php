<?php

declare(strict_types=1);

namespace Pastell\Tests\Connector\IparapheurRest;

use ActionExecutorFactory;
use DonneesFormulaire;
use Exception;
use Fichier;
use FileToSign;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Client\ClientInterface;
use GuzzleHttp\Psr7\Response as HttpResponse;
use Libriciel\IparapheurV5\Client\Model\Action;
use Pastell\Client\IparapheurV5\ApiClientFactory;
use Pastell\Connector\IparapheurRest\IparapheurRestConnector;
use Pastell\Connector\IparapheurRest\IpRestException;
use PastellTestCase;
use Psr\Http\Client\ClientExceptionInterface;
use SignatureException;
use UnrecoverableException;

final class IparapheurRestConnectorTest extends PastellTestCase
{
    private const TENANT_ID = 'tenant-test';
    private const DESK_ID = 'desk-test';
    private const TYPE_ID = 'type-test';
    private const ONGOING_FOLDER_ID = 'ONGOING-FOLDER-ID';
    private const FINISHED_FOLDER_ID = 'FINISHED-FOLDER-ID';
    private const REFUSED_FOLDER_ID = 'REFUSED-FOLDER-ID';
    private const ERROR_FOLDER_ID = 'ERROR-FOLDER-ID';

    public function getConnectorId(): int
    {
        $clientFactory = $this->getObjectInstancier()->getInstance(ApiClientFactory::class);
        $clientFactory->setClientInterface($this->buildFixtureClient());

        $connectorId = $this->createConnector('iparapheur-rest', 'iparapheur REST')['id_ce'];
        $this->configureConnector($connectorId, [
            'url'      => 'https://url',
            'username' => 'username-iparapheur',
            'password' => 'password-iparapheur',
        ]);
        return (int) $connectorId;
    }

    private function buildFixtureClient(): ClientInterface
    {
        $routes = [
            'POST /auth/realms/api/protocol/openid-connect/token' => $this->fixture('authenticate_ok.json'),
            'GET /api/standard/v1/tenant' => $this->fixture('list_tenants.json'),
            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/desk' => $this->fixture('list_user_desks.json'),
            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/types' => $this->fixture('list_types.json'),
            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/types/' . self::TYPE_ID . '/subtypes' => $this->fixture('list_subtypes.json'),
            'POST /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder' => $this->fixture('create_folder_201.json', 201),

            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::ONGOING_FOLDER_ID . '/premis' => $this->fixture('ongoing_folder.xml'),
            'PUT /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::ONGOING_FOLDER_ID . '/task/start-task-id/start' => new HttpResponse(
                200,
                ['Content-type' => 'application/json'],
                ''
            ),
            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::ONGOING_FOLDER_ID . '/zip' => $this->fixture('get_folder_zip.zip'),

            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::FINISHED_FOLDER_ID . '/premis' => $this->fixture('finished_folder.xml'),
            'GET /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::REFUSED_FOLDER_ID . '/premis' => $this->fixture('refused_folder.xml'),

            'DELETE /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::ONGOING_FOLDER_ID => new HttpResponse(204, ['Content-type' => 'application/json'], ''),
            'DELETE /api/standard/v1/tenant/' . self::TENANT_ID . '/desk/' . self::DESK_ID . '/folder/' . self::ERROR_FOLDER_ID => new HttpResponse(400, ['Content-type' => 'application/json'], ''),
        ];

        $client = $this->getMockBuilder(ClientInterface::class)->getMock();
        $client->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use ($routes): ResponseInterface {
                $key = $request->getMethod() . ' ' . $request->getUri()->getPath();
                if (!\array_key_exists($key, $routes)) {
                    throw new UnrecoverableException('Unknown path : ' . $key);
                }
                return $routes[$key];
            });

        return $client;
    }

    private function fixture(string $filename, int $status = 200): ResponseInterface
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $ct = match ($ext) {
            'json' => 'application/json',
            'xml'  => 'application/xml; charset=UTF-8',
            default => 'application/octet-stream',
        };

        return new HttpResponse(
            $status,
            ['Content-type' => $ct],
            file_get_contents(__DIR__ . '/fixtures/' . $filename)
        );
    }


    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    private function makeConnector(array $overrides = []): IparapheurRestConnector
    {
        $connector = new IparapheurRestConnector($this->getObjectInstancier()->getInstance(ApiClientFactory::class));
        $connector->setLogger($this->getLogger());
        $connector->setConnecteurConfig($this->cfg($overrides));
        return $connector;
    }

    private function cfg(array $overrides = []): DonneesFormulaire
    {
        $defaults = [
            'url' => 'https://url',
            'username' => 'username-iparapheur',
            'password' => 'password-iparapheur',
            'tenant_id' => null,
            'desk_id' => null,
            'iparapheur_nb_jour_max' => '',
            'iparapheur_metadata' => '',
            'iparapheur_multi_doc' => false,
            'iparapheur_type_id' => self::TYPE_ID,
        ];
        $map = array_merge($defaults, $overrides);
        $cfg = $this->createStub(DonneesFormulaire::class);
        $cfg->method('get')->willReturnCallback(fn(string $key, $default = null) => $map[$key] ?? $default);
        return $cfg;
    }

    public function testConnexion(): void
    {
        $connectorId = $this->getConnectorId();
        $this->triggerActionOnConnector($connectorId, 'test-connexion');
        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertSame('Liste des entités iparapheur : Pastell, Pastell 2, Pastell 3', $lastMessage);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws IpRestException
     * @throws JsonException
     */
    public function testGetDeskList(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID]);
        $desks = $connector->getDeskList();
        self::assertCount(3, $desks);
    }

    /**
     * @throws IpRestException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testGetTypeList(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID]);
        $types = $connector->getTypeList();
        self::assertNotEmpty($types);
        $firstKey = array_key_first($types);
        self::assertIsString($firstKey);
        self::assertIsString($types[$firstKey]);
    }

    /**
     * @throws IpRestException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testGetSousType(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'iparapheur_type_id' => self::TYPE_ID]);
        $subtypes = $connector->getSousType();
        self::assertNotEmpty($subtypes);
        $firstKey = array_key_first($subtypes);
        self::assertIsString($firstKey);
        self::assertIsString($subtypes[$firstKey]);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testGetDeskListRequiresTenant(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector();
        $this->expectException(IpRestException::class);
        $this->expectExceptionMessage("L'entité iparapheur est obligatoire pour voir la liste des bureaux");
        $connector->getDeskList();
    }

    /**
     * @throws JsonException
     * @throws ClientExceptionInterface
     */
    public function testGetTenantList(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector();
        $expected = [];
        $json = json_decode(file_get_contents(__DIR__ . '/fixtures/list_tenants.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($json['content'] as $t) {
            $expected[$t['id']] = $t['name'];
        }
        $out = $connector->getTenantList();
        self::assertSame($expected, $out);
    }

    /**
     * @throws SignatureException
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testSendDossier(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $doc = new Fichier();
        $doc->filename = 'main.pdf';
        $doc->content = '%PDF-1.4';
        $fts = new FileToSign();
        $fts->document = $doc;
        $fts->annexes = [];
        $fts->type = 'TYPE';
        $fts->sousType = 'SOUS-TYPE';
        $folderId = $connector->sendDossier($fts);
        self::assertSame(self::ONGOING_FOLDER_ID, $folderId);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     * @throws Exception
     */
    public function testGetSignature(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $premis = $connector->getPremis(self::ONGOING_FOLDER_ID);
        $info = $connector->getSignature(self::ONGOING_FOLDER_ID);
        self::assertArrayHasKey('bordereau', $info);
        self::assertSame($info['documents'][0]->filename, $premis->object[1]->originalName);
        self::assertSame($info['annexes'][0]->filename, $premis->object[2]->originalName);
        self::assertEquals($premis, $info['premis']);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testGetPremis(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $premis = $connector->getPremis(self::ONGOING_FOLDER_ID);
        self::assertGreaterThanOrEqual(2, \count($premis->object));
        self::assertSame('intellectualEntity', $premis->object[0]->type);
        self::assertSame('file', $premis->object[1]->type);
        self::assertSame('test', $premis->object[0]->originalName);
        self::assertSame('blank.pdf', $premis->object[1]->originalName);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testGetAllHistoriqueInfo(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $all_historique = $connector->getAllHistoriqueInfo(self::ONGOING_FOLDER_ID);
        self::assertCount(3, $all_historique->LogDossier);
        self::assertSame(Action::START, $all_historique->LogDossier[0]->status);
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function testGetLastHistorique(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $history = $connector->getAllHistoriqueInfo(self::ONGOING_FOLDER_ID);
        $last = $connector->getLastHistorique($history);
        self::assertSame('Étape en cours : [SIGNATURE] ', $last);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testGetRefusalMessage(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        self::assertSame('This folder was refused', $connector->getRefusalMessage(self::REFUSED_FOLDER_ID));
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function testGetDateSignature(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $history = $connector->getAllHistoriqueInfo(self::FINISHED_FOLDER_ID);
        $date = $connector->getDateSignature($history);
        self::assertSame('2025-08-03', $date);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testEffacerDossierRejete(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $ok = $connector->effacerDossierRejete(self::ONGOING_FOLDER_ID);
        self::assertTrue($ok);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function testEffacerDossierRejeteError(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $ok = $connector->effacerDossierRejete(self::ERROR_FOLDER_ID);
        self::assertFalse($ok);
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function testIsFinalState(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $history = $connector->getAllHistoriqueInfo(self::FINISHED_FOLDER_ID);
        $lastHistorique = $connector->getLastHistorique($history);
        self::assertTrue($connector->isFinalState($lastHistorique));
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function testIsNotFinalState(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $history = $connector->getAllHistoriqueInfo(self::ONGOING_FOLDER_ID);
        $lastHistorique = $connector->getLastHistorique($history);
        self::assertFalse($connector->isFinalState($lastHistorique));
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function testIsRejected(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $history = $connector->getAllHistoriqueInfo(self::REFUSED_FOLDER_ID);
        $lastHistorique = $connector->getLastHistorique($history);
        self::assertTrue($connector->isRejected($lastHistorique));
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function testIsNotRejected(): void
    {
        $this->getConnectorId();
        $connector = $this->makeConnector(['tenant_id' => self::TENANT_ID, 'desk_id' => self::DESK_ID]);
        $history = $connector->getAllHistoriqueInfo(self::FINISHED_FOLDER_ID);
        $lastHistorique = $connector->getLastHistorique($history);
        self::assertFalse($connector->isRejected($lastHistorique));
    }
}
