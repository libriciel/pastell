<?php

class AgentSQLTest extends PastellTestCase
{
    private const SIREN = '000000000';

    private function getAgentSQL(): AgentSQL
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

    private function getAgentSQLWithInfo(): AgentSQL
    {
        $agentSQL = $this->getAgentSQL();
        $agentSQL->replace($this->getInfo());
        return $agentSQL;
    }

    /**
     * @deprecated 4.1.26
     */
    public function testAdd(): void
    {
        $this->getAgentSQL()->add($this->getInfo());
        static::assertSame(1, $this->getAgentSQL()->getNbAgent(self::SIREN));
    }

    /**
     * @deprecated 4.1.26
     */
    public function testAddSirenCol(): void
    {
        $info_collectivite = ['siren' => '444444444'];
        $this->getAgentSQL()->add($this->getInfo(), $info_collectivite);
        static::assertSame(0, $this->getAgentSQL()->getNbAgent(self::SIREN));
        static::assertSame(1, $this->getAgentSQL()->getNbAgent('444444444'));
    }

    public function testReplace(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $agent_info = $this->getInfo();
        $agent_info[2] = 'Bond 2';
        $agentSQL->replace($agent_info);
        self::assertSame(1, $agentSQL->getNbAgent(self::SIREN));
        self::assertSame('Bond 2', $agentSQL->getAll(self::SIREN)[0]['nom_usage']);
    }

    public function testCleanAll(): void
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $agentSQL->cleanAll();
        self::assertSame(0, $agentSQL->getNbAgent(self::SIREN));
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
        $nb_agent = $agentSQL->getNbAllAgent('Bond');
        static::assertEquals(1, $nb_agent);
    }

    public function testgetAllAgent()
    {
        $agentSQL = $this->getAgentSQLWithInfo();
        $result = $agentSQL->getAllAgent('Bond', 0);
        static::assertEquals('Bond', $result[0]['nom_usage']);
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
