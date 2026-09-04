<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\SimpleTwigRenderer;

use Error;
use Exception;
use Pastell\Service\SimpleTwigRenderer;
use Pastell\Service\SimpleTwigRenderer\SimpleTwigJsonPath;
use PastellTestCase;
use UnrecoverableException;

final class SimpleTwigJsonPathTest extends PastellTestCase
{
    private string $json_file;
    private string $method;

    protected function setUp(): void
    {
        \error_reporting(E_ALL);
        parent::setUp();
        $this->json_file = file_get_contents(__DIR__ . '/test.json');
        $this->method = SimpleTwigJsonPath::JSONPATH_FUNCTION;
    }

    public function twigRenderer(): SimpleTwigRenderer
    {
        return $this->getObjectInstancier()->getInstance(SimpleTwigRenderer::class);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testOK(): void
    {
        $form = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $form->addFileFromData(
            'json',
            'test.json',
            $this->json_file
        );
        $expression = "{{ $this->method('json', '$.phoneNumbers[:1].type') }}";
        self::assertSame('iPhone', $this->twigRenderer()->render($expression, $form));
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testError(): void
    {
        $form = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $form->addFileFromData(
            'json',
            'test.json',
            $this->json_file
        );

        $expression = "{{ $this->method('json', '$.phoneNumbers') }}";

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            //phpcs:ignore Generic.Files.LineLength.MaxExceeded
            'Erreur sur le template {{ jsonpath(\'json\', \'$.phoneNumbers\') }} : An exception has been thrown during the rendering of a template ("Array to string conversion")'
        );
        $this->twigRenderer()->render($expression, $form);
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function testNull(): void
    {
        $form = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $form->addFileFromData(
            'json',
            'test.json',
            $this->json_file
        );
        $expression = "{{ $this->method('json', '$.home[:1].type') }}";
        self::assertSame('', $this->twigRenderer()->render($expression, $form));
    }
}
