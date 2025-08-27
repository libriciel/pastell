<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use ChoiceActionExecutor;
use Exception;
use IparapheurRest;
use Pastell\Connector\IparapheurRest\IpRestException;

class IpRestGetSubTypeList extends ChoiceActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): bool
    {
        throw new IpRestException('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function display(): bool
    {
        throw new IpRestException('Not implemented');
    }

    /**
     * @throws Exception
     */
    public function displayAPI(): array
    {
        /** @var IparapheurRest $connector */
        $connector = $this->getMyConnecteur();
        return $connector->getSousType();
    }
}
