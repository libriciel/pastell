<?php

class HttpApiTest extends PastellTestCase
{
    /** @var  HttpApi */
    private $http_api;

    protected function setUp(): void
    {
        parent::setUp();
        $apiAuthetication = $this->createMock(ApiAuthentication::class);
        $apiAuthetication->method('getUtilisateurId')->willReturn(1);
        $this->getObjectInstancier()->setInstance(ApiAuthentication::class, $apiAuthetication);
    }

    private function getCall($ressource, $method = 'GET')
    {
        $this->http_api = new HttpApi($this->getObjectInstancier());
        $this->http_api->setServerArray(['REQUEST_METHOD' => $method]);
        $this->http_api->setGetArray([HttpApi::PARAM_API_FUNCTION => $ressource]);
        $this->http_api->dispatch();
    }

    public function testCallMethod()
    {
        $this->expectOutputRegex("#1.4-fixtures#");
        $this->getCall("/v2/version");
    }

    public function testNotFound()
    {
        $this->expectOutputRegex("#HTTP/1.1 404 Not Found#");
        $this->getCall("/v2/foo");
    }

    public function testCallBaMethod()
    {
        $this->expectOutputRegex("#HTTP/1.1 405 Method Not Allowed#");
        $this->getCall("/v2/version", "PATCH");
    }

    public function testCallNotAuthenticated(): void
    {
        $apiAuthetication = $this->createMock(ApiAuthentication::class);
        $apiAuthetication->method('getUtilisateurId')->willThrowException(new UnauthorizedException());
        $this->getObjectInstancier()->setInstance(ApiAuthentication::class, $apiAuthetication);

        $this->expectOutputRegex('#HTTP/1.1 401 Unauthorized#');
        $this->getCall('/v2/version');
    }


    public function testDispatchFailed()
    {
        $this->expectOutputRegex("#HTTP/1.1 400 Bad Request#");
        $this->getCall("/v2/");
    }

    public function testAPIV1()
    {
        $this->expectOutputRegex("#1.4-fixtures#");
        $this->getCall("/version.php");
    }

    public function testAPIV1NotFound()
    {
        $this->expectOutputRegex("#HTTP/1.1 404 Not Found#");
        $this->getCall("/foo.php");
    }

    public function testDispatchAllo()
    {
        $this->expectOutputRegex("#1.4-fixtures#");
        $this->getCall("rest/allo");
    }

    public function testDispatchEmptyRequest()
    {
        $this->expectOutputRegex("#HTTP/1.1 400 Bad Request#");
        $this->getCall("");
    }

    public function testBadVersion()
    {
        $this->expectOutputRegex("#HTTP/1.1 400 Bad Request#");
        $this->getCall("/v3/version");
    }

    public function testJournalWhenDeletedDocument()
    {
        /*
            En mode API, l'id_u dans le journal n'était pas setté correctement,
            le script init des tests initialise l'id_u du journal.
        */
        $this->getJournalEntryService()->setId(0);

        $id_d = $this->createDocument('test')['id_d'];

        ob_start();
        $this->getCall("/v2/entite/1/document/$id_d/action/supression", 'POST');
        ob_end_clean();

        $all = $this->getJournalConsultationService()->getList(0, '', 0, 0, 0, 10);
        $this->assertEquals(1, $all[0]['id_u']);
    }

    public function testGetDocumentEndingWithPhp(): void
    {
        $this->getCall('/v2/entite/1/document/jfkvphp');
        $this->expectOutputRegex('/HTTP\/1.1 404 Not Found/');
    }
}
