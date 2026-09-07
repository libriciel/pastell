<?php

use Pastell\Configuration\ConnectorValidation;

class ConnecteurFileTest extends PastellTestCase
{
    /**
     * @dataProvider filesEntitiesProvider
     * @throws UnrecoverableException
     */
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
        // GLOB_BRACE is not available on musl-based systems (Alpine), so patterns are expanded manually.
        $directories = ['/connecteur/*/', '/extensions/*/build/connecteur/*/'];
        $filenames = [
            ConnecteurDefinitionFiles::ENTITE_PROPERTIES_FILENAME,
            ConnecteurDefinitionFiles::GLOBAL_PROPERTIES_FILENAME,
        ];
        foreach ($directories as $directory) {
            foreach ($filenames as $filename) {
                foreach (glob(PASTELL_PATH . $directory . $filename) ?: [] as $filePath) {
                    yield basename(dirname($filePath)) . '/' . basename($filePath) => [$filePath];
                }
            }
        }
    }
}
