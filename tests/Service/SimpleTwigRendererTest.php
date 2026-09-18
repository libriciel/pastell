<?php

namespace Pastell\Tests\Service;

use DonneesFormulaireException;
use Exception;
use Generator;
use NotFoundException;
use Pastell\Service\SimpleTwigRenderer;
use Pastell\Service\SimpleTwigRendererExemple;
use PastellTestCase;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use UnrecoverableException;

class SimpleTwigRendererTest extends PastellTestCase
{
    protected function setUp(): void
    {
        \error_reporting(E_ALL);
        SimpleTwigRenderer\SimpleTwigXpathCommon::clearCache();
    }

    public static function renderDataProvider(): Generator
    {
        $xpath = '//*[local-name()="ActeRecu"]/@*[local-name()="Date"]';

        yield ["",""];
        yield ["constante","constante"];
        yield ["Services d'aide et d'accompagnement à domicile (SAAD)","{{ variable }}"];
        yield [
            "Arrêté individuel Bond James (matricule 007)",
            "Arrêté individuel {{ nom_agent }} {{ prenom_agent}} (matricule {{ matricule_agent }})",
        ];
        yield [
            'foo 12 buz',
            "foo {{ xpath('pes_aller','//EnTetePES/CodBud/@V') }} buz"
        ];
        yield 'with_jsonpath' => [
            'foo 19.95 buz',
            "foo {{ jsonpath('test_json','$.store.bicycle.price') }} buz"
        ];
        yield 'jsonpath_array_of_scalars' => [
            'Nigel Rees, Evelyn Waugh',
            "{{ jsonpath_array('test_json', '$.store.book[:2].author')|join(', ') }}",
        ];
        // A JSON object is returned as a Flow\JSONPath\JSONPath instance: the sandbox must let us read its keys
        yield 'jsonpath_object_dot_access' => [
            'red',
            "{{ jsonpath('test_json', '$.store.bicycle').color }}",
        ];
        yield 'jsonpath_object_bracket_access' => [
            'red',
            "{{ jsonpath('test_json', '$.store.bicycle')['color'] }}",
        ];
        yield 'jsonpath_array_of_objects_in_for_loop' => [
            'Sayings of the Century;Sword of Honour;',
            "{% for book in jsonpath_array('test_json', '$.store.book[:2]') %}{{ book.title }};{% endfor %}",
        ];
        yield 'jsonpath_array_of_objects_first' => [
            'Nigel Rees',
            "{{ jsonpath_array('test_json', '$.store.book[*]')|first.author }}",
        ];
        yield [
            '',"{{ not_existing_value }}"
        ];
        yield [
            'foo  bar','foo {{ xpath("pes_aller","//NotExistingPath") }} bar'
        ];
        yield [
            'foo  bar','foo {{ jsonpath("test_json","$.notExistingPath") }} bar'
        ];
        yield [
            '','{{ jsonpath("not_existing_file","$.notExistingPath")}}'
        ];
        yield [
            'Durand','{{ csvpath("test_csv_with_comma",1,1) }}'
        ];
        yield [
            'Durand','{{ csvpath("test_csv_with_semicolon",1,1,";") }}'
        ];
        yield [
            'Michel;Michele','{{ csvpath("test_csv_with_comma",0,3) }}'
        ];
        yield [
            '','{{ csvpath("test_csv_with_comma",42,0) }}'
        ];
        yield [
            '','{{ csvpath("test_csv_with_comma",0,42) }}'
        ];
        yield 'csv_path_with_not_existing_file' => [
            '','{{ csvpath("not_existing_file",1,2) }}'
        ];
        yield 'csvpath_in_expression' => [
            'true','{% if (csvpath("test_csv_with_semicolon",0,1,";")  == "Michel") %}true{% else %}false{% endif %}'
        ];
        yield 'csvpath_in_false_expression' => [
            'false','{% if (csvpath("test_csv_with_semicolon",0,1,";")  == "Jean-Pierre") %}true{% else %}false{% endif %}'
        ];
        yield 'xpath_with_namespaces' => [
            '2017-12-07',"{{ xpath( 'aractes' , '$xpath' ) }}"
        ];
        yield 'xpath_without_namespaces' => [
            '2017-12-27',"{{ xpath( 'aractes' , '/actes:ARActe/@actes:DateReception' ) }}"
        ];
        yield 'xpath_array' => [
            '3, 2',"{{ xpath_array( 'aractes' , '//*/@actes:CodeMatiere' ) | join(', ') }}"
        ];
        yield 'ls_unique_filter' => [
            '3, 1, 2',"{{ [ 3, 1, 2, 1, 3, 2] | ls_unique | join(', ') }}"
        ];
        yield 'test_other_metadata' => [
            'Eric Lyon',"{{ pa_user_name }} {{ pa_entity_name }}"
        ];
        yield 'tests_are_allowed' => [
            'defined,empty,null,even,same,divisible,iterable,default',
            "{{ nom_agent is defined ? 'defined' }},{{ '' is empty ? 'empty' }},{{ not_existing_value is null ? 'null' }},"
            . "{{ 2 is even ? 'even' }},{{ 1 is same as(1) ? 'same' }},{{ 4 is divisible by(2) ? 'divisible' }},"
            . "{{ [] is iterable ? 'iterable' }},{{ not_existing_value ?? 'default' }}",
        ];
    }

    /**
     * @param string $expected_result
     * @param $template
     * @throws DonneesFormulaireException
     * @throws LoaderError
     * @throws SyntaxError
     * @dataProvider renderDataProvider
     */
    public function testRender(string $expected_result, $template)
    {
        $simpleTwigRenderer = new SimpleTwigRenderer();

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $donneesFormulaire->setTabData(
            [
                'nom_agent' => 'Bond',
                'prenom_agent' => 'James',
                'matricule_agent' => '007',
                'variable' => "Services d'aide et d'accompagnement à domicile (SAAD)"
            ]
        );
        $donneesFormulaire->addFileFromCopy(
            'pes_aller',
            'pes.xml',
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1595923133_1646706116.xml"
        );
        $donneesFormulaire->addFileFromCopy(
            'test_json',
            'test_json.json',
            __DIR__ . "/fixtures/test.json"
        );

        $donneesFormulaire->addFileFromCopy(
            'test_csv_with_comma',
            'test_csv_with_comma.csv',
            __DIR__ . "/fixtures/test-with-coma.csv"
        );

        $donneesFormulaire->addFileFromCopy(
            'test_csv_with_semicolon',
            'test_csv_with_semicolon.csv',
            __DIR__ . "/fixtures/test-with-semicolon.csv"
        );
        $donneesFormulaire->addFileFromCopy(
            'aractes',
            'aractes.xml',
            __DIR__ . "/fixtures/aractes.xml"
        );

        $other_metadata = [
          'pa_user_name' => 'Eric',
          'pa_entity_name' => 'Lyon'
        ];

        $this->assertEquals(
            $expected_result,
            $simpleTwigRenderer->render(
                $template,
                $donneesFormulaire,
                $other_metadata
            )
        );
    }

    /**
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function testRenderWhenNotATwigExpression()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Erreur de syntaxe sur le template twig ligne 1<br />
Message d\'erreur : Unclosed "variable".<br />
<br />
<br />
<br />
<b>1. {{dsfdsf </b><em>^^^ Unclosed "variable".</em><br />
<br />
');
        $simpleTwigRenderer->render("{{dsfdsf ", $donneesFormulaire);
    }

    public function testRenderWhenARuntimeExpressionIsThrown()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage('Erreur sur le template');
        echo $simpleTwigRenderer->render(
            "{{ range(0,jsonpath('fichier_json','$.Liste_sous_traitants.length')) }} ",
            $donneesFormulaire
        );
    }

    /**
     * @throws DonneesFormulaireException
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function testRenderWhenNotAXPathExpression()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromCopy(
            'pes_aller',
            'pes.xml',
            __DIR__ . "/fixtures/HELIOS_SIMU_ALR2_1595923133_1646706116.xml"
        );
        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("xpath(): Invalid expression");
        $simpleTwigRenderer->render("{{ xpath('pes_aller','/////EnTetePES/CodBud/@V') }}", $donneesFormulaire);
    }

    /**
     * @throws LoaderError
     * @throws SyntaxError
     * @throws Exception
     */
    public function testXPathOnNonXMLFile()
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromData(
            'pes_aller',
            'pes.xml',
            "toto"
        );
        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage("Le fichier pes_aller n'est pas un fichier XML");
        $simpleTwigRenderer->render("{{ xpath('pes_aller','//EnTetePES/CodBud/@V') }}", $donneesFormulaire);
    }

    /**
     * @throws LoaderError
     * @throws SyntaxError
     * @throws NotFoundException
     */
    public function testRenderWithFormulaire()
    {
        $id_d = $this->createDocument('actes-generique')['id_d'];

        $template1 = "Conseil municipal de la ville TRUC - Titre : {{ titre }} - Date : {{ date_de_lacte }} - select {{ acte_nature }}";

        $this->configureDocument($id_d, [
            'titre' => "toto",
            'acte_nature' => '3',
            'date_de_lacte' => '2020-12-25',
            'classification' => '8.2',
            'objet' => $template1
        ]);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);

        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->assertEquals(
            3,
            $simpleTwigRenderer->render("{{ acte_nature }}", $donneesFormulaire)
        );
        $this->assertEquals(
            "Actes individuels",
            $simpleTwigRenderer->render("{{ select_value('acte_nature') }}", $donneesFormulaire)
        );

        $this->assertEquals(
            '2020-12-25',
            $simpleTwigRenderer->render("{{ date_de_lacte }}", $donneesFormulaire)
        );
        $this->assertEquals(
            "Conseil municipal de la ville TRUC - Titre :  - Date : 2020-12-25 - select 3",
            $simpleTwigRenderer->render($donneesFormulaire->get('objet'), $donneesFormulaire)
        );
    }

    public static function exempleProvider(): Generator
    {
        $simpleTwigRendererExemple = new SimpleTwigRendererExemple();
        foreach ($simpleTwigRendererExemple->getExemple() as $key => $exemple) {
            unset($exemple[1]);
            yield $key => $exemple;
        }
    }

    /**
     * @throws LoaderError
     * @throws SyntaxError
     * @dataProvider exempleProvider
     */
    public function testExemple(string $expression, array $data)
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->setTabData($data[0]);
        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->assertEquals(
            $data[1],
            $simpleTwigRenderer->render($expression, $donneesFormulaire)
        );
    }

    public static function forbiddenTemplateDataProvider(): Generator
    {
        yield 'read_a_php_constant' => [
            'Function "constant" is not allowed',
            "{{ constant('BD_PASS') }}",
        ];
        yield 'read_a_file_with_a_php_callable_in_map' => [
            'must be a Closure in sandbox mode',
            "{{ ['/etc/passwd']|map('file_get_contents')|join }}",
        ];
        yield 'call_a_php_callable_through_call_user_func_in_map' => [
            'must be a Closure in sandbox mode',
            '{{ {"/etc/passwd":"file_get_contents"}|map("call_user_func")|join }}',
        ];
        yield 'read_a_file_with_a_php_callable_in_filter' => [
            'must be a Closure in sandbox mode',
            "{{ ['/etc/passwd']|filter('file_get_contents')|join }}",
        ];
        yield 'write_a_file_with_a_php_callable_in_sort' => [
            'must be a Closure in sandbox mode',
            "{{ ['/tmp/pastell-sandbox-escape', 'pwned']|sort('file_put_contents')|join }}",
        ];
        yield 'read_a_file_with_the_source_function' => [
            'Function "source" is not allowed',
            "{{ source('/etc/passwd') }}",
        ];
        yield 'read_a_file_with_the_include_function' => [
            'Function "include" is not allowed',
            "{{ include('/etc/passwd') }}",
        ];
        yield 'read_a_file_with_the_include_tag' => [
            'Tag "include" is not allowed',
            "{% include '/etc/passwd' %}",
        ];
        yield 'call_a_php_callable_with_the_invoke_filter' => [
            'Filter "invoke" is not allowed',
            "{{ 'file_get_contents'|invoke('/etc/passwd') }}",
        ];
        yield 'read_a_php_enum' => [
            'Function "enum" is not allowed',
            "{{ enum('Pastell\\\\Seda\\\\SedaVersion').cases()|join }}",
        ];
        yield 'guess_a_php_constant_with_the_constant_test' => [
            'Test "constant" is not allowed',
            "{{ 'pastell' is constant('BD_PASS') ? 'yes' : 'no' }}",
        ];
        yield 'extend_a_template' => [
            'Tag "extends" is not allowed',
            "{% extends '/etc/passwd' %}",
        ];
        // The "use" tag resolves its template while parsing, so the empty loader refuses it before the sandbox does
        yield 'use_a_template' => [
            'Tag "use" is not allowed.',
            "{% use '/etc/passwd' %}",
        ];
    }

    /**
     * @dataProvider forbiddenTemplateDataProvider
     * @throws DonneesFormulaireException
     */
    public function testRenderWithAForbiddenExpression(string $expected_message, string $template): void
    {
        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $simpleTwigRenderer = new SimpleTwigRenderer();
        $this->expectException(UnrecoverableException::class);
        $this->expectExceptionMessage($expected_message);
        $simpleTwigRenderer->render($template, $donneesFormulaire);
    }

    /**
     * SimpleXMLElement::asXML() writes the file it is given: only the string conversion of the nodes returned by
     * xpath() is allowed.
     * @throws DonneesFormulaireException
     */
    public function testRenderCanNotCallAMethodOnAnXMLNode(): void
    {
        $written_file = '/tmp/pastell-sandbox-escape.xml';
        $this->assertFileDoesNotExist($written_file);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $donneesFormulaire->addFileFromCopy(
            'aractes',
            'aractes.xml',
            __DIR__ . '/fixtures/aractes.xml'
        );

        $simpleTwigRenderer = new SimpleTwigRenderer();
        try {
            $simpleTwigRenderer->render(
                "{{ xpath_array('aractes', '//*/@actes:CodeMatiere')|first.asXML('$written_file') }}",
                $donneesFormulaire
            );
            $this->fail('The asXML() method should not be allowed');
        } catch (UnrecoverableException $e) {
            $this->assertStringContainsString('"asxml" method on a "SimpleXMLElement" object is not allowed', $e->getMessage());
        }
        $this->assertFileDoesNotExist($written_file);
    }
}
