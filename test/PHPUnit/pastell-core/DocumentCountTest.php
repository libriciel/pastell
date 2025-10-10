<?php

class DocumentCountTest extends PastellTestCase
{
    public function testCountAll(): void
    {
        $this->createDocument('actes-generique');
        $documentCount = $this->getObjectInstancier()->getInstance(DocumentCount::class);
        $result = $documentCount->getAll(1);
        static::assertEquals(
            [
                1 =>
                     [
                        'flux' =>
                             [
                                'actes-automatique' =>
                                     [],
                                'actes-generique' =>
                                     [
                                        'creation' => '1',
                                    ],
                                'actes-preversement-seda' =>
                                     [],
                                'helios-automatique' =>
                                     [],
                                'helios-generique' =>
                                     [],
                                'pdf-generique' =>
                                     [],
                                'mailsec' =>
                                     [],
                                'test' =>
                                     [],
                                'document-a-signer' => [],
                                'actes-reponse-prefecture' => [],
                                'commande-generique' => [],
                                'mailsec-bidir' => [],
                                'ls-document-pdf' => []
                            ],
                        'info' =>
                             [
                                'id_e' => 1,
                                'type' => 'collectivite',
                                'denomination' => 'Bourg-en-Bresse',
                                'siren' => '000000000',
                                'date_inscription' => '0000-00-00 00:00:00',
                                'entite_mere' => '0',
                                'centre_de_gestion' => '0',
                                'is_active' => '1',
                            ],
                    ],
                2 =>
                     [
                        'flux' =>
                             [
                                'actes-automatique' =>
                                     [],
                                'actes-generique' =>
                                     [],
                                'actes-preversement-seda' =>
                                     [],
                                'helios-automatique' =>
                                     [],
                                'helios-generique' =>
                                     [],
                                'pdf-generique' =>
                                     [],
                                'mailsec' =>
                                     [],
                                'test' =>
                                     [],
                                'document-a-signer' => [],
                                'actes-reponse-prefecture' => [],
                                'commande-generique' => [],
                                'mailsec-bidir' => [],
                                'ls-document-pdf' => []
                            ],
                        'info' =>
                             [
                                'id_e' => 2,
                                'type' => 'collectivite',
                                'denomination' => 'CCAS',
                                'siren' => '111111118',
                                'date_inscription' => '0000-00-00 00:00:00',
                                'entite_mere' => '1',
                                'centre_de_gestion' => '0',
                                'is_active' => '1',
                            ],
                    ],
             ],
            $result
        );
    }

    public function testCountLimit(): void
    {
        $this->createDocument('actes-generique');
        $documentCount = $this->getObjectInstancier()->getInstance(DocumentCount::class);
        $result = $documentCount->getAll(1, 1, 'actes-generique');
        static::assertSame(
            [
                1 =>
                     [
                        'flux' =>
                             [
                                'actes-generique' =>
                                     [
                                        'creation' => 1,
                                    ],
                            ],
                        'info' =>
                             [
                                'id_e' => '1',
                                'type' => 'collectivite',
                                'denomination' => 'Bourg-en-Bresse',
                                'siren' => '000000000',
                                'date_inscription' => '0000-00-00 00:00:00',
                                'entite_mere' => '0',
                                'centre_de_gestion' => '0',
                                'is_active' => true,
                            ],
                    ]
             ],
            $result
        );
    }
}
