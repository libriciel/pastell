<?php

class AgentSQLTest extends PastellTestCase
{
    private const SIREN = '000000000';

    private function getAgentSQL()
    {
        $sqlQuery = $this->getObjectInstancier()->getInstance(SQLQuery::class);
        return new AgentSQL($sqlQuery);
    }

    private function getInfo(): array
    {
        return [
            '007',
            'M.',
            'Bond',
            'Bond',
            'James',
            'XYZ',
            'Agent secret',
            'UK',
            "Royaume-Uni de Grande Bretagne et d'Irlande du Nord",
            '000000000',
            '2',
            'Libelle',
            '42',
            '43',
        ];
    }

    private function getAgentSQLWithInfo()
    {
        $agentSQL = $this->getAgentSQL();
        $agentSQL->add($this->getInfo());
        return $agentSQL;
    }

    public function testAdd(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        static::assertSame(1, $agentSQL->getNbAgent(self::SIREN));
    }

    public function testAddSirenCol(): void
    {
        $info_collectivite = ['siren' => '444444444'];
        $this->getAgentSQL()->add($this->getInfo(), $info_collectivite);
        static::assertSame(0, $this->getAgentSQL()->getNbAgent(self::SIREN));
        static::assertSame(1, $this->getAgentSQL()->getNbAgent('444444444'));
    }

    public function testGetBySiren(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $result = $agentSQL->getBySiren('000000000', 0);
        static::assertSame('Bond', $result[0]['nom_usage']);
    }

    public function testGetInfo(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $result = $agentSQL->getInfo(1, self::SIREN);
        static::assertSame('Bond', $result['nom_usage']);
    }

    public function testNbAllAgent()
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $nb_agent = $agentSQL->getNbAllAgent("Bond");
        $this->assertEquals(1, $nb_agent);
    }

    public function testgetAllAgent()
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $result = $agentSQL->getAllAgent("Bond", 0);
        $this->assertEquals("Bond", $result[0]['nom_usage']);
    }

    public function testGetAll(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $result = $agentSQL->getAll(self::SIREN);
        static::assertSame('Bond', $result[0]['nom_usage']);
    }

    public function testClean(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        static::assertSame(1, $this->getAgentSQL()->getNbAgent(self::SIREN));
        $agentSQL->clean(self::SIREN);
        static::assertSame(0, $this->getAgentSQL()->getNbAgent(self::SIREN));
    }
}
