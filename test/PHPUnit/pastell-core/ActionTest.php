<?php

use Pastell\Service\Droit\DroitService;
use Pastell\Service\Droit\DroitType;

class ActionTest extends PHPUnit\Framework\TestCase
{
    /** @var  Action */
    private $action;

    protected function setUp(): void
    {
        parent::setUp();
        $yamlLoader = new YMLLoader(new MemoryCacheNone());
        $array = $yamlLoader->getArray(__DIR__ . "/fixtures/definition-for-action-test.yml");
        $this->action = new Action($array['action']);
    }

    public function testGetAll()
    {
        $result = $this->action->getAll();
        $this->assertEquals('modification', $result[0]);
    }

    public function testGetActionName()
    {
        $this->assertEquals(
            "En cours de rédaction",
            $this->action->getActionName('modification')
        );
    }

    public function testGetActionNameIdAction()
    {
        $this->assertEquals(
            'action-without-name',
            $this->action->getActionName('action-without-name')
        );
    }

    public function testGetActionNameFatalError()
    {
        $this->assertEquals(
            'Erreur fatale',
            $this->action->getActionName('fatal-error')
        );
    }

    public function testGetDoActionName()
    {
        $this->assertEquals(
            "Modifier",
            $this->action->getDoActionName('modification')
        );
    }

    public function testGetDoActionNameIdAction()
    {
        $this->assertEquals(
            "action-without-name",
            $this->action->getDoActionName('action-without-name')
        );
    }

    public function testGetActionRule()
    {
        $result = $this->action->getActionRule('modification');
        static::assertEquals(DroitService::getDroitFor('test', DroitType::LECTURE), $result['droit_id_u']);
    }
}
