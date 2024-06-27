<?php

class IparapheurSubType extends ChoiceActionExecutor
{
    /**
     * @throws Exception
     */
    public function go()
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function display()
    {
        throw new \RuntimeException('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function displayAPI()
    {
        /** @var IParapheur $signature */
        $signature = $this->getMyConnecteur();
        return $signature->getSousType();
    }
}
