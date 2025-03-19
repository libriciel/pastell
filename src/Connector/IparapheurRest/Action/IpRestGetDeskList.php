<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use DictionnaryChoice;
use Exception;
use IparapheurRest;

class IpRestGetDeskList extends DictionnaryChoice
{
    public function getElementId(): string
    {
        return 'desk_id';
    }

    public function getElementName(): string
    {
        return 'desk_name';
    }

    public function getTitle(): string
    {
        return 'Sélectionner un bureau';
    }

    /**
     * @throws Exception
     */
    public function displayAPI(): array
    {
        /** @var IparapheurRest $connector */
        $connector = $this->getMyConnecteur();
        return $connector->getDeskList();
    }
}
