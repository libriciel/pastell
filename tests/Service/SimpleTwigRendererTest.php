<?php

namespace Pastell\Tests\Service;

use DonneesFormulaireException;
use Exception;
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

    public function renderDataProvider(): array
    {
        $xpath = '//*[local-name()="ActeRecu"]/@*[local-name()="Date"]';

        return [
            ["",""],
            ["constante","constante"],
            ["Services d'aide et d'accompagnement à domicile (SAAD)","{{ variable }}"],
            [
                "Arrêté individuel Bond James (matricule 007)",
                "Arrêté individuel {{ nom_agent }} {{ prenom_agent}} (matricule {{ matricule_agent }})",
            ],
            [
                'foo 12 buz',
                "foo {{ xpath('pes_aller','//EnTetePES/CodBud/@V') }} buz"
            ],
            'with_jsonpath' => [
            'foo 19.95 buz',
                "foo {{ jsonpath('test_json','$.store.bicycle.price') }} buz"
            ],
            [
                '',"{{ not_existing_value }}"
            ],
            [
                'foo  bar','foo {{ xpath("pes_aller","//NotExistingPath") }} bar'
            ],
            [
                'foo  bar','foo {{ jsonpath("test_json","$.notExistingPath") }} bar'
            ],
            [
                '','{{ jsonpath("not_existing_file","$.notExistingPath")}}'
            ],
            [
                'Durand','{{ csvpath("test_csv_with_comma",1,1) }}'
            ],
            [
                'Durand','{{ csvpath("test_csv_with_semicolon",1,1,";") }}'
            ],
            [
                'Michel;Michele','{{ csvpath("test_csv_with_comma",0,3) }}'
            ],
            [
                '','{{ csvpath("test_csv_with_comma",42,0) }}'
            ],
            [
                '','{{ csvpath("test_csv_with_comma",0,42) }}'
            ],
            'csv_path_with_not_existing_file' => [
                '','{{ csvpath("not_existing_file",1,2) }}'
            ],
            'csvpath_in_expression' => [
                'true','{% if (csvpath("test_csv_with_semicolon",0,1,";")  == "Michel") %}true{% else %}false{% endif %}'
            ],
            'csvpath_in_false_expression' => [
                'false','{% if (csvpath("test_csv_with_semicolon",0,1,";")  == "Jean-Pierre") %}true{% else %}false{% endif %}'
            ],
            'xpath_with_namespaces' => [
                '2017-12-07',"{{ xpath( 'aractes' , '$xpath' ) }}"
            ],
            'xpath_without_namespaces' => [
                '2017-12-27',"{{ xpath( 'aractes' , '/actes:ARActe/@actes:DateReception' ) }}"
            ],
            'xpath_array' => [
                '3, 2',"{{ xpath_array( 'aractes' , '//*/@actes:CodeMatiere' ) | join(', ') }}"
            ],
            'ls_unique_filter' => [
                '3, 1, 2',"{{ [ 3, 1, 2, 1, 3, 2] | ls_unique | join(', ') }}"
            ],
            'test_other_metadata' => [
                'Eric Lyon',"{{ pa_user_name }} {{ pa_entity_name }}"
            ]
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
        echo $simpleTwigRenderer->render("{{ range(0,jsonpath('fichier_json','$.Liste_sous_traitants.length')) }} ", $donneesFormulaire);
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

    public function exempleProvider(): array
    {
        $simpleTwigRendererExemple = new SimpleTwigRendererExemple();
        return  array_map(
            function ($a) {
                    unset($a[1]);
                    return $a;
            },
            $simpleTwigRendererExemple->getExemple()
        );
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
}
