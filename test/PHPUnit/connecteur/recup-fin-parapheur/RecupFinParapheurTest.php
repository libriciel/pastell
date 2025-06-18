<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Pastell\Client\IparapheurV5\ClientFactory;
use Psr\Http\Client\ClientInterface;

class RecupFinParapheurTest extends PastellTestCase
{
    private TmpFolder $tmpFolder;
    private string $workspace_path;

    /** @throws Exception */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpFolder = new TmpFolder();
        $this->workspace_path = $this->tmpFolder->create();
        $this->getObjectInstancier()->setInstance('workspacePath', $this->workspace_path);
    }

    protected function tearDown(): void
    {
        $this->tmpFolder->delete($this->workspace_path);
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function testRecupOne(): void
    {
        $clientInterface = $this->getMockBuilder(ClientInterface::class)->getMock();
        $clientInterface->method('sendRequest')
            ->willReturnCallback(function (Request $request): Response {
                return match ($request->getUri()->getPath()) {
                    '/auth/realms/api/protocol/openid-connect/token' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/fixtures/authenticate_ok.json')
                    ),
                    '/api/standard/v1/tenant//desk//FINISHED' => new Response(
                        200,
                        ['Content-type' => 'application/json'],
                        file_get_contents(__DIR__ . '/fixtures/list-folders.json')
                    ),
                    '/api/standard/v1/tenant//desk/desk_id/folder/82bd1f75-8c09-11ed-9e3a-0242ac150013/zip' => new Response(
                        200,
                        ['Content-type' => 'application/pdf'],
                        file_get_contents(__DIR__ . '/../../../../tests/Client/IparapheurV5/fixtures/response.zip')
                    ),
                    '/api/standard/v1/tenant//desk//folder/82bd1f75-8c09-11ed-9e3a-0242ac150013' => new Response(
                        204,
                    ),
                    default => throw new UnrecoverableException('Unknown path : ' . $request->getUri()->getPath()),
                };
            });
        $clientFactory = $this->getObjectInstancier()->getInstance(ClientFactory::class);
        $clientFactory->setClientInterface($clientInterface);

        $id_ce = $this->createConnector('recup-fin-parapheur', 'Recup fin parapheur')['id_ce'];
        $this->configureConnector($id_ce, ['url' => 'https://aaaa.bbb', 'pastell_module_id' => 'ls-recup-parapheur']);
        $this->triggerActionOnConnector($id_ce, 'recup_one');
        $lastMessage = $this->getObjectInstancier()->getInstance(ActionExecutorFactory::class)->getLastMessage();
        self::assertMatchesRegularExpression('#^Création des documents : #', $lastMessage);
        preg_match('/: .*?- (.*?)$/s', $lastMessage, $matches);
        $id_d = $matches[1];
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        self::assertSame('TEST 1', $donneesFormulaire->getTitre());
        self::assertSame('60124458-8687-11ed-b28f-0242c0a8b013', $donneesFormulaire->get('dossier_id'));
        self::assertFileEquals(
            __DIR__ . '/fixtures/i_Parapheur_internal_premis.xml',
            $donneesFormulaire->getFilePath('premis')
        );
    }
}
