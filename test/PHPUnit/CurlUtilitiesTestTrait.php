<?php

use PHPUnit\Framework\MockObject\MockObject;

trait CurlUtilitiesTestTrait
{
    protected function mockCurl(array $url_to_content, $error_code = 200): CurlWrapper&MockObject
    {
        return $this->mockCurlWithCallable(
            function ($url) use ($url_to_content) {
                if (! isset($url_to_content[$url])) {
                    throw new UnrecoverableException("Appel à une URL inatendue $url");
                }
                return $url_to_content[$url];
            },
            $error_code
        );
    }

    protected function mockCurlWithCallable(callable $get_function, $error_code = 200): CurlWrapper&MockObject
    {
        $curlWrapper = $this->createMock(CurlWrapper::class);

        $curlWrapper->expects($this->any())
            ->method('get')
            ->willReturnCallback($get_function);

        $curlWrapper->expects($this->any())
            ->method('getHTTPCode')
            ->willReturn($error_code);

        $curlWrapper->expects($this->any())
            ->method('getLastHTTPCode')
            ->willReturn($error_code);

        $curlWrapper->expects($this->any())
            ->method('getFullMessage')
            ->willReturn(sprintf('Code HTTP: %s.', $error_code));

        $curlWrapperFactory = $this->createMock(CurlWrapperFactory::class);

        $curlWrapperFactory->expects($this->any())
            ->method('getInstance')
            ->willReturn($curlWrapper);

        $this->getObjectInstancier()->setInstance(CurlWrapperFactory::class, $curlWrapperFactory);

        return $curlWrapper;
    }
}
