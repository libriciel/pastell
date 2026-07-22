<?php

use Dkd\PhpCmis\Data\FolderInterface;

class DepotCMISTest extends PastellTestCase
{
    private function getDepotCMIS(?FolderInterface $folder = null): DepotCMIS
    {
        $depotCMIS = new DepotCMIS($this->getDonneesFormulaireFactory());
        if ($folder !== null) {
            $folderProperty = new ReflectionProperty(DepotCMIS::class, 'folder');
            $folderProperty->setAccessible(true);
            $folderProperty->setValue($depotCMIS, $folder);
        }
        return $depotCMIS;
    }

    private function getWithoutDeprecationWarnings(): ReflectionMethod
    {
        $method = new ReflectionMethod(DepotCMIS::class, 'withoutDeprecationWarnings');
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Une dépréciation émise pendant un appel CMIS (par ex. par libriciel/php-cmis via
     * League\Uri\Modifier) ne doit pas polluer le retour de l'API : le connecteur doit
     * l'avoir masquée dans error_reporting au moment où elle est déclenchée.
     */
    public function testListDirectorySuppressesDeprecationWarnings(): void
    {
        $folder = $this->createMock(FolderInterface::class);
        $folder->method('getChildren')->willReturnCallback(function () {
            trigger_error('appel déprécié depuis php-cmis', E_USER_DEPRECATED);
            return [
                new class {
                    public function getName(): string
                    {
                        return 'foo';
                    }
                },
                new class {
                    public function getName(): string
                    {
                        return 'bar';
                    }
                },
            ];
        });

        $depotCMIS = $this->getDepotCMIS($folder);

        // On rejoue ici ce que font le gestionnaire d'erreur PHP par défaut et celui de
        // PHPUnit : une dépréciation n'est prise en compte (donc affichée / polluante) que
        // si le bit correspondant est présent dans error_reporting().
        $leakedDeprecations = [];
        set_error_handler(
            static function (int $errno, string $errstr) use (&$leakedDeprecations): bool {
                if (error_reporting() & $errno) {
                    $leakedDeprecations[] = $errstr;
                }
                return true;
            },
            E_DEPRECATED | E_USER_DEPRECATED
        );
        try {
            $result = $depotCMIS->listDirectory();
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['foo', 'bar'], $result);
        $this->assertSame([], $leakedDeprecations, 'Aucune dépréciation ne doit polluer le retour');
    }

    public function testWithoutDeprecationWarningsMasksDeprecationLevels(): void
    {
        $depotCMIS = $this->getDepotCMIS();
        $method = $this->getWithoutDeprecationWarnings();

        $reportingInsideCallback = $method->invoke($depotCMIS, static fn () => error_reporting());

        $this->assertSame(0, $reportingInsideCallback & E_DEPRECATED);
        $this->assertSame(0, $reportingInsideCallback & E_USER_DEPRECATED);
    }

    public function testWithoutDeprecationWarningsKeepsOtherErrorLevels(): void
    {
        $depotCMIS = $this->getDepotCMIS();
        $method = $this->getWithoutDeprecationWarnings();

        $previous = error_reporting(E_ALL);
        try {
            $reportingInsideCallback = $method->invoke($depotCMIS, static fn () => error_reporting());
        } finally {
            error_reporting($previous);
        }

        $this->assertSame(E_WARNING, $reportingInsideCallback & E_WARNING);
        $this->assertSame(E_NOTICE, $reportingInsideCallback & E_NOTICE);
    }

    public function testWithoutDeprecationWarningsReturnsCallbackResult(): void
    {
        $depotCMIS = $this->getDepotCMIS();
        $method = $this->getWithoutDeprecationWarnings();

        $this->assertSame('resultat', $method->invoke($depotCMIS, static fn () => 'resultat'));
    }

    public function testWithoutDeprecationWarningsRestoresReporting(): void
    {
        $depotCMIS = $this->getDepotCMIS();
        $method = $this->getWithoutDeprecationWarnings();

        $before = error_reporting();
        $method->invoke($depotCMIS, static fn () => null);

        $this->assertSame($before, error_reporting());
    }

    public function testWithoutDeprecationWarningsRestoresReportingOnException(): void
    {
        $depotCMIS = $this->getDepotCMIS();
        $method = $this->getWithoutDeprecationWarnings();

        $before = error_reporting();
        try {
            $method->invoke($depotCMIS, static function (): void {
                throw new RuntimeException('boom');
            });
            $this->fail('Une exception était attendue');
        } catch (RuntimeException $exception) {
            $this->assertSame('boom', $exception->getMessage());
        }

        $this->assertSame($before, error_reporting());
    }
}
