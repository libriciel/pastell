<?php

class ConnecteurFrequenceSQLTest extends PastellTestCase
{
    private ConnecteurFrequenceSQL $connecteurFrequenceSQL;
    private int $id_cf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        $this->id_cf = $this->connecteurFrequenceSQL->create(
            'entite',
            'signature',
            'i-parapheur',
            1,
            'document',
            'actes-generique',
            'verif-signature',
            '',
            '',
        );
    }

    public function testDelete()
    {
        $this->connecteurFrequenceSQL->delete($this->id_cf);
        self::assertFalse($this->connecteurFrequenceSQL->getInfo($this->id_cf));
    }

    public function testCreate(): void
    {
        $id_cf = $this->connecteurFrequenceSQL->create(
            'entite',
            'TdT',
            's2low',
            42,
            'document',
            'helios-generique',
            'verif-tdt',
            '30',
            'TDT'
        );

        $info = $this->connecteurFrequenceSQL->getInfo($id_cf);
        self::assertSame('s2low', $info['id_connecteur']);
        self::assertSame(42, $info['id_ce']);
        self::assertSame('TDT', $info['id_verrou']);
    }

    public function testUpdateColumns(): void
    {
        $this->connecteurFrequenceSQL->update($this->id_cf, 'global', 'TdT', '', 0, '', '', '', '60', 'TDT');

        $info = $this->connecteurFrequenceSQL->getInfo($this->id_cf);
        self::assertSame('global', $info['type_connecteur']);
        self::assertSame(0, $info['id_ce']);
        self::assertSame('60', $info['expression']);
    }

    public function testGetCandidateList(): void
    {
        $candidate_list = \array_column(
            $this->connecteurFrequenceSQL->getCandidateList(
                'ENTITE',
                'Signature',
                'i-parapheur',
                1,
                'document',
                'actes-generique',
                'autre'
            ),
            null,
            'id_cf'
        );

        self::assertSame(1, $candidate_list[$this->id_cf]['famille_connecteur_match']);
        self::assertSame(0, $candidate_list[$this->id_cf]['action_match']);
        self::assertSame(0, $candidate_list[$this->id_cf]['type_connecteur_blank']);
        self::assertArrayNotHasKey(
            $this->id_cf,
            $this->connecteurFrequenceSQL->getCandidateList('entite', 'signature', 'i-parapheur', 2, '', '', '')
        );
    }
}
