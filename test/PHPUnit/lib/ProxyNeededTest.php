<?php

namespace PHPUnit\lib;

use ProxyNeeded;
use PHPUnit\Framework\TestCase;

class ProxyNeededTest extends TestCase
{
    public static function dataProvider(): array
    {
        return [
            'without_proxy' => ['','','http://localhost',false],
            'proxy' => ['toto','','http://localhost',true],
            'in_no_proxy' => ['toto','localhost','http://localhost',false],
            'not_in_no_proxy' => ['toto','localhost','http://127.0.0.1',true],
        ];
    }

    /**
     * @param string $http_proxy_url
     * @param string $no_proxy
     * @param string $url
     * @param bool $expectedResult
     * @dataProvider dataProvider
     */
    public function testOK(string $http_proxy_url, string $no_proxy, string $url, bool $expectedResult)
    {
        $proxy = new ProxyNeeded($http_proxy_url, $no_proxy);
        $this->assertEquals($expectedResult, $proxy->isNeeded($url));
    }
}
