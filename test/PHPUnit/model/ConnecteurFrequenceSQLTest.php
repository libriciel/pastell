<?php

class ConnecteurFrequenceSQLTest extends PastellTestCase
{
    /** @var  ConnecteurFrequenceSQL */
    private $connecteurFrequenceSQL;

    /** @var  ConnecteurFrequence */
    private $connecteurFrequence;

    private $id_cf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connecteurFrequenceSQL = $this->getObjectInstancier()->getInstance(ConnecteurFrequenceSQL::class);
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'signature';
        $connecteurFrequence->id_connecteur = 'i-parapheur';
        $connecteurFrequence->id_ce = 1;
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'actes-generique';
        $connecteurFrequence->action = 'verif-signature';

        $this->id_cf = $this->connecteurFrequenceSQL->edit($connecteurFrequence);
        $this->connecteurFrequence = $this->connecteurFrequenceSQL->getConnecteurFrequence($this->id_cf);
    }

    public function testUpdate()
    {
        $this->connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_GLOBAL;
        $this->connecteurFrequenceSQL->edit($this->connecteurFrequence);
        $connecteurFrequence = $this->connecteurFrequenceSQL->getConnecteurFrequence($this->id_cf);
        $this->assertEquals(ConnecteurFrequence::TYPE_GLOBAL, $connecteurFrequence->type_connecteur);
    }

    public function testDelete()
    {
        $this->connecteurFrequenceSQL->delete($this->id_cf);
        $this->assertNull($this->connecteurFrequenceSQL->getConnecteurFrequence($this->id_cf));
    }

    public function testGetAll()
    {
        $all = $this->connecteurFrequenceSQL->getAll();
        $this->assertEquals(1, $all[0]->id_cf);
    }

    public function testGetNearestConnecteurFromConnecteur()
    {
        $resultConnecteurFrequence = $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($this->connecteurFrequence);
        $this->assertEquals($this->id_cf, $resultConnecteurFrequence->id_cf);
        foreach ($this->connecteurFrequence->getAttributeName() as $key) {
            $this->assertEquals($this->connecteurFrequence->$key, $resultConnecteurFrequence->$key);
        }
    }

    public function testGetNearestConnecteurNoConnecteur()
    {
        foreach ($this->connecteurFrequenceSQL->getAll() as $connecteurFrequence) {
            $this->connecteurFrequenceSQL->delete($connecteurFrequence->id_cf);
        };
        $this->assertNull($this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($this->connecteurFrequence));
    }

    public function testGetNearestConnecteurDifferentConnecteur()
    {
        $connecteurFrequence2 = new ConnecteurFrequence();
        $connecteurFrequence2->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence2->famille_connecteur = 'TdT';
        $connecteurFrequence2->id_connecteur = 's2low';
        $connecteurFrequence2->id_ce = 42;
        $connecteurFrequence2->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence2->type_document = 'helios-generique';
        $connecteurFrequence2->action = 'verif-tdt';

        $resultConnecteurFrequence = $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence2);
        $this->assertEquals('DEFAULT_FREQUENCE', $resultConnecteurFrequence->id_verrou);
    }

    public function testGetNearestConnecteurNearestAction()
    {
        $connecteurFrequence2 = new ConnecteurFrequence();
        $connecteurFrequence2->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence2->famille_connecteur = 'signature';
        $connecteurFrequence2->id_connecteur = 'i-parapheur';
        $connecteurFrequence2->id_ce = 1;
        $connecteurFrequence2->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence2->type_document = 'helios-generique';
        $connecteurFrequence2->action = 'verif-signature';

        $resultConnecteurFrequence = $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence2);
        $this->assertEquals('DEFAULT_FREQUENCE', $resultConnecteurFrequence->id_verrou);
    }

    public function testGetNearestConnecteurPartialConnecteurPartialAction()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'SAE';


        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'helios-generique';

        $id_cf = $this->connecteurFrequenceSQL->edit($connecteurFrequence);

        $connecteurFrequence->id_connecteur = 'As@lae';
        $connecteurFrequence->id_ce = 1;
        $connecteurFrequence->action = 'verif-signature';

        $this->assertEquals($id_cf, $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence)->id_cf);
    }

    public function testGetNearestConnecteurPartialConnecteur()
    {
        $connecteurFrequence = new ConnecteurFrequence();
        $connecteurFrequence->type_connecteur = ConnecteurFrequence::TYPE_ENTITE;
        $connecteurFrequence->famille_connecteur = 'signature';

        $id_cf = $this->connecteurFrequenceSQL->edit($connecteurFrequence);

        $connecteurFrequence->id_connecteur = 'i-Parapheur';
        $connecteurFrequence->id_ce = 1;
        $connecteurFrequence->action = 'verif-signature';
        $connecteurFrequence->action_type = ConnecteurFrequence::TYPE_ACTION_DOCUMENT;
        $connecteurFrequence->type_document = 'helios-generique';

        $this->assertEquals($id_cf, $this->connecteurFrequenceSQL->getNearestConnecteurFromConnecteur($connecteurFrequence)->id_cf);
    }
}
