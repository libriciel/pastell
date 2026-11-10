<?php

use Pastell\Configuration\ConnectorValidation;
use PHPUnit\Framework\Attributes\DataProvider;

class ConnecteurFileTest extends PastellTestCase
{
    /**
     * @throws UnrecoverableException
     */
    #[DataProvider('filesEntitiesProvider')]
    public function testAllConnecteur(string $filePath): void
    {
        $connectorValidation = $this->getObjectInstancier()->getInstance(ConnectorValidation::class);
        self::assertNotEmpty($connectorValidation->getConfiguration($filePath));
    }

    /**
     * The data provider must be static: it is called before the test case is instantiated.
     */
    public static function filesEntitiesProvider(): Generator
    {
        $pattern = '/{,extensions/*/build/}connecteur/*/{'
            . ConnecteurDefinitionFiles::ENTITE_PROPERTIES_FILENAME . ','
            . ConnecteurDefinitionFiles::GLOBAL_PROPERTIES_FILENAME . '}';
        foreach (glob(PASTELL_PATH . $pattern, GLOB_BRACE) as $filePath) {
            yield basename(dirname($filePath)) . '/' . basename($filePath) => [$filePath];
        }
    }
}
