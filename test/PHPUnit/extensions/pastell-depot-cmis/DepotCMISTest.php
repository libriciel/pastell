<?php

use PHPUnit\Framework\Attributes\IgnoreDeprecations;

final class DepotCMISTest extends PastellTestCase
{
    /**
     * Guzzle 5 (pulled in by dkd/php-cmis) triggers PHP 8.1 deprecations when the client is built.
     * Nothing can be done on our side until dkd/php-cmis is updated or replaced.
     * https://packagist.org/packages/dkd/php-cmis
     *
     * @throws Exception
     */
    #[IgnoreDeprecations]
    public function testExtensionIsScoped(): void
    {
        $this->getObjectInstancier()->getInstance(Extensions::class)->autoloadExtensions();
        $connector = $this->createConnector('depot-cmis', 'test');
        /** @var DepotCMIS $class */
        $class = $this->getConnecteurFactory()->getConnecteurById($connector['id_ce']);

        $this->assertInstanceOf(PastellExtension\PastellDepotCmis\GuzzleHttp\Client::class, $class->getClient());
    }
}
