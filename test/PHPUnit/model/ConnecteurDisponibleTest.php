<?php

class ConnecteurDisponibleTest extends PastellTestCase
{
    /**
     * @return ConnecteurDisponible
     */
    private function getConnecteurDisponible()
    {
        return $this->getObjectInstancier()->getInstance(ConnecteurDisponible::class);
    }

    public function testGetConnecteurDisponible()
    {
        $result = $this->getConnecteurDisponible()->getListByType(1, 1, 'mailsec', false);
        $this->assertEquals("Mail securise", $result[0]['libelle']);
    }

    public function testGetConnecteurDisponibleNoRight()
    {
        $this->assertEmpty($this->getConnecteurDisponible()->getListByType(3, 1, 'mailsec', false));
    }

    public function testGetConnecteurDisponibleInherited()
    {
        $result = $this->getConnecteurDisponible()->getListByType(1, 2, 'mailsec', false);
        $this->assertEquals("Mail securise", $result[0]['libelle']);
    }
}
