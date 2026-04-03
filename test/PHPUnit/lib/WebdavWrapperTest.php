<?php

use Sabre\DAV\Client;
use Sabre\HTTP\ClientHttpException;
use Sabre\HTTP\Response;

class WebdavWrapperTest extends PHPUnit\Framework\TestCase
{
    /**
     * @throws Exception
     */
    public function testExists()
    {
        $client = $this->createMock(Client::class);

        $client
            ->method('propFind')
            ->willReturn([]);

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('i', 'j', 'k');
        $this->assertTrue($webdavWrapper->exists("test"));
    }

    /**
     * @throws Exception
     */
    public function testException()
    {
        $client = $this->createMock(Client::class);

        $client
            ->method('propFind')
            ->willThrowException(new ClientHttpException(new Response()));

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('i', 'j', 'k');
        $this->expectException(Exception::class);
        $webdavWrapper->exists("test");
    }


    /**
     * @test
     * @throws Exception
     */
    public function whenWebdavConnectionIsOk()
    {
        $client = $this->createMock(Client::class);

        $client
            ->method('send')
            ->willReturn(new Response(200, ['Dav' => 'test']));

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld', '', '');

        $this->assertTrue($webdavWrapper->isConnected());
    }


    /**
     * When we don't get a webdav header
     *
     * @test
     * @throws Exception
     */
    public function whenNoDavHeaderExist()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Le serveur ne présente pas le header Dav");

        $client = $this->createMock(Client::class);

        $client
            ->method('send')
            ->willReturn(new Response(200));


        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld', '', '');

        $webdavWrapper->isConnected();
    }

    /**
     * When we are not authorized to authenticate to the webdav server
     *
     * @test
     * @throws Exception
     */
    public function whenDavAccessIsForbidden()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("403 : Forbidden");

        $client = $this->createMock(Client::class);

        $client
            ->method('send')
            ->willReturn(new Response(403));


        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld', '', '');

        $webdavWrapper->isConnected();
    }

    /**
     * @throws Exception
     */
    public function testGet()
    {
        $client = $this->createMock(Client::class);
        $response = new Response(200);
        $response->setBody('content');

        $client
            ->method('send')
            ->willReturn($response);

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld', '', '');

        $this->assertSame('content', $webdavWrapper->get('test.xml'));
    }

    /**
     * @throws Exception
     */
    public function testGetException()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("403 : Forbidden");

        $client = $this->createMock(Client::class);

        $client
            ->method('send')
            ->willReturn(new Response(403));

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld', '', '');

        $webdavWrapper->get('test.xml');
    }

    public function testSetDataConnexionEncodesSpacesInPath(): void
    {
        $capturedSettings = [];

        $client = $this->createMock(Client::class);

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturnCallback(function (array $settings) use (&$capturedSettings, $client) {
                $capturedSettings = $settings;
                return $client;
            });

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld/mon repertoire/sous dossier', '', '');

        $this->assertSame('https://domain.tld/mon%20repertoire/sous%20dossier/', $capturedSettings['baseUri']);
    }

    /**
     * @throws ClientHttpException
     */
    public function testPropfind()
    {
        $client = $this->createMock(Client::class);

        $client
            ->method('propfind')
            ->willReturn([
                '/path/to/file1.xml' => [
                    '{DAV:}getlastmodified' => 'Thu, 23 May 2019 09:48:18 GMT',
                    '{DAV:}getcontentlength' => '988',
                    '{DAV:}getcontenttype' => 'application/xml'
                ],
                '/path/to/file2.xml' => [
                    '{DAV:}getlastmodified' => 'Thu, 23 May 2019 09:48:18 GMT',
                    '{DAV:}getcontenttype' => 'application/xml'
                ],
            ]);

        $webdavClientFactory = $this->createMock(WebdavClientFactory::class);
        $webdavClientFactory
            ->method('getInstance')
            ->willReturn($client);

        $webdavWrapper = new WebdavWrapper();
        $webdavWrapper->setWebdavClientFactory($webdavClientFactory);
        $webdavWrapper->setDataConnexion('https://domain.tld', '', '');

        $this->assertSame(
            [
                'file1.xml' => [
                    '{DAV:}getlastmodified' => 'Thu, 23 May 2019 09:48:18 GMT',
                    '{DAV:}getcontentlength' => '988',
                    '{DAV:}getcontenttype' => 'application/xml'
                ],
                'file2.xml' => [
                    '{DAV:}getlastmodified' => 'Thu, 23 May 2019 09:48:18 GMT',
                    '{DAV:}getcontentlength' => null,
                    '{DAV:}getcontenttype' => 'application/xml'
                ]
            ],
            $webdavWrapper->propfind(
                '',
                [
                    '{DAV:}getlastmodified',
                    '{DAV:}getcontentlength',
                    '{DAV:}getcontenttype'
                ],
                1
            )
        );
    }
}
