<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\SimpleTwigRenderer;

use Error;
use Exception;
use Pastell\Service\SimpleTwigRenderer;
use Pastell\Service\SimpleTwigRenderer\SimpleTwigJsonPathArray;
use PastellTestCase;
use UnrecoverableException;

class SimpleTwigJsonPathArrayTest extends PastellTestCase
{
    private string $json_file;
    private string $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->json_file = file_get_contents(__DIR__ . '/test.json');
        $this->method = SimpleTwigJsonPathArray::JSONPATH_ARRAY_FUNCTION;
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
        $expression = <<<EOT
{% for element in $this->method('json', '$.phoneNumbers') %}
{% for phoneNumber in element %}
Type: {{ phoneNumber.type }}
Number: {{ phoneNumber.number }}
{% endfor %}
{% endfor %}
EOT;
        self::assertSame('Type: iPhone
Number: 0123-4567-8888
Type: home
Number: 0123-4567-8910
', $this->twigRenderer()->render($expression, $form));
        self::assertSame('Type: iPhone
Number: 0123-4567-8888
Type: home
Number: 0123-4567-8910
', $this->twigRenderer()->render($expression, $form));
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

        $expression = "{{ $this->method('json', '$.toto') }}";

        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage(
            //phpcs:ignore Generic.Files.LineLength.MaxExceeded
            'Erreur sur le template {{ jsonpath_array(\'json\', \'$.toto\') }} : An exception has been thrown during the rendering of a template ("Object of class Flow\JSONPath\JSONPath could not be converted to string")'
        );
        $this->twigRenderer()->render($expression, $form);
    }
}
