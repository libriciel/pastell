<?php

class CurlWrapperTest extends PHPUnit\Framework\TestCase
{
    public function testGet()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction->method("curl_getinfo")->willReturn("200");

        $curlWrapper = new CurlWrapper($curlFunction);

        $curlWrapper->httpAuthentication("eric", "mdp");
        $curlWrapper->addHeader("key", "value");
        $curlWrapper->setAccept("format");
        $curlWrapper->dontVerifySSLCACert();
        $curlWrapper->setClientCertificate("x", "y", "z");
        $curlWrapper->setServerCertificate("y");

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
        $this->assertEquals("OK", $curlWrapper->getLastOutput());
        $this->assertEquals("200", $curlWrapper->getLastHttpCode());
    }

    public function testGetFailed()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_error")->willReturn("Connexion impossible");
        $curlWrapper = new CurlWrapper($curlFunction);

        $this->assertFalse($curlWrapper->get("http://pastell.adullact.org"));
        $this->assertEquals("Erreur de connexion au serveur : Connexion impossible", $curlWrapper->getLastError());
    }


    public function testGet404()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_getinfo")->willReturn("404");
        $curlFunction->method("curl_exec")->willReturn("not found");
        $curlWrapper = new CurlWrapper($curlFunction);

        $this->assertFalse($curlWrapper->get("http://pastell.adullact.org"));
        $this->assertEquals("http://pastell.adullact.org : 404 Not Found", $curlWrapper->getLastError());
        $this->assertEquals("not found", $curlWrapper->getLastOutput());
    }

    public function testPost()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction->method('curl_setopt')->willReturnCallback(function ($curlHandle, $properties, $values) {
            if ($properties != CURLOPT_POSTFIELDS) {
                return;
            }
            $this->assertEquals(['foo' => 'bar'], $values);
        });

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->addPostData("foo", "bar");

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
    }

    public function testPostFile()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction->method('curl_setopt')->willReturnCallback(function ($curlHandle, $properties, $values) {
            if ($properties != CURLOPT_POSTFIELDS) {
                return;
            }
            $this->assertEquals("a.txt", $values['foo']->postname);
        });

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/a.txt", "a.txt");


        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
    }

    public function testPostURLEncode()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->setPostDataUrlEncode(["foo" => "bar"]);

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
    }

    public function testPostDataWithSimilarName()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction->method('curl_setopt')->willReturnCallback(function ($curlHandle, $properties, $values) {
            if ($properties != CURLOPT_POSTFIELDS) {
                return;
            }
            $this->assertMatchesRegularExpression("#foo.*bar.*foo.*baz#ms", $values);
        });
        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->addPostData("foo", "bar");
        $curlWrapper->addPostData("foo", "baz");

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
    }

    public function testPostDataWithSimilarFilename()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->addPostFile("foo", __FILE__, false, "text/plain", "binary");
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/autorite-cert.pem");

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
    }

    public function testPostSameFile()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");

        $last_body = "";
        $curlFunction
            ->method("curl_setopt")
            ->willReturnCallback(function ($a, $b, $c) use (&$last_body) {
                if ($b !== CURLOPT_PROXY) {
                    $last_body = $c;
                }
            });

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/autorite-cert.pem");
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/autorite-cert.pem");

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
        $this->assertMatchesRegularExpression("#foo.*foo#ms", $last_body);
    }

    public function testGetHTTPCode()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction->method("curl_getinfo")->willReturn("403");
        $curlWrapper = new CurlWrapper($curlFunction);

        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
        $this->assertEquals("403", $curlWrapper->getHTTPCode());
    }

    public function testConstruct()
    {
        $curlWrapper = new CurlWrapper();
        $this->assertInstanceOf(CurlWrapper::class, $curlWrapper);
    }


    public function testsetJsonPostData()
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction->method("curl_getinfo")->willReturn("200");

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->setJsonPostData(['foo' => 'bar','baz' => 'buz']);
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $this->assertEquals("OK", $curlWrapper->get("http://pastell.adullact.org"));
    }

    public function testKeepAttachmentOrder()
    {
        $curlFunction = $this->createMock(CurlFunctions::class);
        $curlFunction->method('curl_setopt')->willReturnCallback(function ($curlHandle, $properties, $values) {
            if ($properties != CURLOPT_POSTFIELDS) {
                return;
            }
            $this->assertMatchesRegularExpression("#aaa.*bbbb.*aaa#s", $values);
        });

        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/a.txt", "a");
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/b.txt", "b");
        $curlWrapper->addPostFile("foo", __DIR__ . "/fixtures/a.txt", "a");
        $curlWrapper->get("url");
    }

    public function proxyDataProvider()
    {
        yield 'proxy' => ["mon_proxy","mon_proxy","","url"];
        yield "proxy_url_in_no_proxy" => ["","mon_proxy","my_host","https://my_host:443/toto"];
        yield "no_proxy" => ["","","","url"];
    }

    /**
     * @dataProvider proxyDataProvider
     */
    public function testWithProxy(string $expected_setopt, string $proxy_value, string $no_proxy, string $url_called)
    {
        $curlFunction = $this->createMock("CurlFunctions");
        $curlFunction->method("curl_exec")->willReturn("OK");
        $curlFunction
            ->method("curl_setopt")
            ->willReturnCallback(function ($ch, $name, $value) use ($expected_setopt) {
                if ($name === CURLOPT_PROXY) {
                    $this->assertEquals($expected_setopt, $value);
                }
            });
        $curlWrapper = new CurlWrapper($curlFunction);
        $curlWrapper->setProxy($proxy_value);
        $curlWrapper->setNoProxy($no_proxy);
        $curlWrapper->get($url_called);
    }

    public function testInvalidScheme(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid URL: 'file://test'. Only 'http' and 'https' schemes are allowed.");
        $curlWrapper = new CurlWrapper();
        $curlWrapper->get('file://test');
    }

    public function testCustomHeadersArePreservedWithSimilarFilenames(): void
    {
        $capturedHeaders = null;

        $curlFunction = $this->createMock(CurlFunctions::class);
        $curlFunction->method('curl_exec')->willReturn('OK');
        $curlFunction->method('curl_getinfo')->willReturn('200');
        $curlFunction->method('curl_setopt')
            ->willReturnCallback(function ($curlHandle, $option, $value) use (&$capturedHeaders) {
                if ($option === CURLOPT_HTTPHEADER) {
                    $capturedHeaders = $value;
                }
                return true;
            });

        $curlWrapper = new CurlWrapper($curlFunction);

        $curlWrapper->addHeader('X-Custom-Header', 'custom-value');
        $curlWrapper->addHeader('Authorization', 'Bearer token123');

        $curlWrapper->addPostFile('documents', __DIR__ . '/fixtures/a.txt', 'a.txt');
        $curlWrapper->addPostFile('documents', __DIR__ . '/fixtures/b.txt', 'b.txt');

        $curlWrapper->get('http://example.com/upload');

        $this->assertIsArray($capturedHeaders);
        $this->assertContains('X-Custom-Header: custom-value', $capturedHeaders);
        $this->assertContains('Authorization: Bearer token123', $capturedHeaders);

        $contentLengthFound = false;
        $expectFound = false;
        $contentTypeFound = false;

        foreach ($capturedHeaders as $header) {
            if (str_starts_with($header, 'Content-Length:')) {
                $contentLengthFound = true;
            }
            if (str_starts_with($header, 'Expect:')) {
                $expectFound = true;
            }
            if (str_starts_with($header, 'Content-Type: multipart/form-data')) {
                $contentTypeFound = true;
            }
        }

        $this->assertTrue($contentLengthFound);
        $this->assertTrue($expectFound);
        $this->assertTrue($contentTypeFound);
    }

    /**
     * @throws ReflectionException
     */
    public function testAddPostFilePreservesContentType(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');

        try {
            $capturedPostFields = null;

            $curlFunction = $this->createMock(CurlFunctions::class);
            $curlFunction->method('curl_exec')->willReturn('OK');
            $curlFunction->method('curl_getinfo')->willReturn('200');
            $curlFunction->method('curl_setopt')
                ->willReturnCallback(function ($curlHandle, $option, $value) use (&$capturedPostFields) {
                    if ($option === CURLOPT_POSTFIELDS) {
                        $capturedPostFields = $value;
                    }
                    return true;
                });

            $curlWrapper = new CurlWrapper($curlFunction);
            $curlWrapper->addPostFile('document', $tmpFile, 'test.pdf', 'application/pdf');

            $curlWrapper->get('https://example.com/upload');

            $this->assertIsArray($capturedPostFields);
            $this->assertArrayHasKey('document', $capturedPostFields);
            $this->assertInstanceOf(CURLFile::class, $capturedPostFields['document']);
            $this->assertEquals('application/pdf', $capturedPostFields['document']->getMimeType());
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
