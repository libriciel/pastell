<?php

declare(strict_types=1);

class S2lowActesTest extends PastellTestCase
{
    use CurlUtilitiesTestTrait;

    /**
     * @throws DonneesFormulaireException
     * @throws S2lowException
     */
    public function testPostActesOK(): void
    {
        $curlWrapper = $this->mockCurl([
            '/modules/actes/actes_classification_fetch.php?api=1' =>
                file_get_contents(__DIR__ . '/fixtures/classification-exemple.xml'),
            '/admin/users/api-list-login.php' => true,
            '/modules/actes/actes_transac_create.php' => "OK\n666",
        ]);

        $addPostDataCall = [];
        $curlWrapper
            ->method('addPostData')
            ->willReturnCallback(static function ($key, $value) use (&$addPostDataCall) {
                $addPostDataCall[$key] = $value;
                return true;
            });

        $form = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();
        $form->addFileFromCopy(
            'classification_file',
            'classification.xml',
            __DIR__ . '/fixtures/classification-exemple.xml'
        );

        $s2low = new S2low($this->getObjectInstancier());
        $s2low->setConnecteurConfig($form);

        $acte = new TdtActes();
        $acte->acte_nature = '3';
        $acte->numero_de_lacte = '201903251130';
        $acte->objet = 'TEST';
        $acte->date_de_lacte = '2019-03-25';
        $acte->classification = '2.1';
        $acte->arrete = new Fichier();
        $acte->arrete->filepath = __DIR__ . '/fixtures/classification-exemple.xml';
        $acte->arrete->filename = 'test.pdf';

        $annexe = new Fichier();
        $annexe->filepath = __DIR__ . '/fixtures/classification-exemple.xml';
        $annexe->filename = 'annexe1.pdf';

        $acte->autre_document_attache = [$annexe];

        static::assertSame('666', $s2low->sendActes($acte));

        static::assertSame(
            [
                'api' => 1,
                'nature_code' => '3',
                'number' => '201903251130',
                'subject' => 'TEST',
                'decision_date' => '2019-03-25',
                'en_attente' => 0,
                'document_papier' => 0,
                'classif1' => '2',
                'classif2' => '1',
            ],
            $addPostDataCall
        );
    }
}
