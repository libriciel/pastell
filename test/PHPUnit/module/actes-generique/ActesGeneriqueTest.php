<?php

declare(strict_types=1);

class ActesGeneriqueTest extends PastellTestCase
{
    public const FLUX_ID = 'actes-generique';

    public function testCasNominal(): void
    {

        $result = $this->getInternalAPI()->post('/Document/' . PastellTestCase::ID_E_COL, ['type' => self::FLUX_ID]);
        static::assertNotEmpty($result['id_d']);

        $info['id_d'] = $result['id_d'];
        $info['id_e'] = PastellTestCase::ID_E_COL;
        $info['acte_nature'] = 1;
        $info['numero_de_lacte'] = 'TEST20131202A';
        $info['objet'] = "Test d'un actes soumis au contrôle de légalité";
        $info['date_de_lacte'] = '2013-12-02';
        $info['envoi_signature'] = 1;
        $info['envoi_tdt'] = 1;
        $info['envoi_sae'] = 1;
        $info['envoi_ged']  = 1;
        $info['classification'] =  '2.1 Documents d urbanisme';
        $info['iparapheur_type'] = 'Actes';
        $info['iparapheur_sous_type'] = 'Deliberation';

        $result = $this->getInternalAPI()->patch(
            "/Document/{$info['id_e']}/actes-generique/{$info['id_d']}",
            $info
        );

        static::assertSame('Test d\'un actes soumis au contrôle de légalité', $result['content']['data']['objet']);

        $uploaded_file = $this->getEmulatedDisk() . '/tmp/Delib Adullact.pdf';
        copy(__DIR__ . '/fixtures/Delib Adullact.pdf', $uploaded_file);
        $result = $this->getInternalAPI()->post(
            "/Document/{$info['id_e']}/actes-generique/{$info['id_d']}/file/arrete",
            ['file_name' => 'Delib Adullact.pdf','file_content' => file_get_contents($uploaded_file)]
        );
        static::assertSame('Delib Adullact.pdf', $result['content']['data']['arrete'][0]);
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testVersementSAEWithoutConnecteurTdt(): void
    {

        $this->getInternalAPI()->delete('/entite/1/flux?id_fe=2');

        $id_d = $this->createDocument(self::FLUX_ID)['id_d'];

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donnesFormulaire->addFileFromData('arrete', 'actes.pdf', 'foo');
        $donnesFormulaire->addFileFromData('autre_document_attache', 'annexe1.pdf', 'bar');
        $donnesFormulaire->addFileFromData('autre_document_attache', 'annexe1.pdf', 'baz', 1);

        $this->getInternalAPI()->patch(
            "/entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['99_AI','99_AU','22_ZZ']]
        );

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        static::assertSame('99_AI', $donnesFormulaire->get('type_acte'));
        static::assertSame('["99_AU","22_ZZ"]', $donnesFormulaire->get('type_pj'));
        static::assertSame('3 fichier(s) typé(s)', $donnesFormulaire->get('type_piece'));
        static::assertSame(
            '[{"filename":"actes.pdf","typologie":"99_AI"},{"filename":"annexe1.pdf","typologie":"99_AU"},{"filename":"annexe1.pdf","typologie":"22_ZZ"}]',
            $donnesFormulaire->getFileContent('type_piece_fichier')
        );
    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function testVersementSAEWithoutConnecteurTdtOldAPI(): void
    {
        $this->getInternalAPI()->delete('/entite/1/flux?id_fe=2');

        $id_d = $this->createDocument(self::FLUX_ID)['id_d'];

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        $donnesFormulaire->addFileFromData('arrete', 'actes.pdf', 'foo');
        $donnesFormulaire->addFileFromData('autre_document_attache', 'annexe1.pdf', 'bar');
        $donnesFormulaire->addFileFromData('autre_document_attache', 'annexe1.pdf', 'baz', 1);

        $this->getInternalAPI()->patch(
            "/entite/1/document/$id_d/externalData/type_piece",
            ['type_pj' => ['99_AI','99_AU', '22_ZZ']]
        );

        $donnesFormulaire = $this->getDonneesFormulaireFactory()->get($id_d);
        static::assertSame('99_AI', $donnesFormulaire->get('type_acte'));
        static::assertSame('["99_AU","22_ZZ"]', $donnesFormulaire->get('type_pj'));
        static::assertSame('3 fichier(s) typé(s)', $donnesFormulaire->get('type_piece'));
        static::assertSame(
            '[{"filename":"actes.pdf","typologie":"99_AI"},{"filename":"annexe1.pdf","typologie":"99_AU"},{"filename":"annexe1.pdf","typologie":"22_ZZ"}]',
            $donnesFormulaire->getFileContent('type_piece_fichier')
        );
    }
}
