<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use DictionnaryChoice;
use Exception;
use Pastell\Connector\IparapheurRest\IpRestDeskInterface;
use Pastell\Connector\IparapheurRest\IpRestException;

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
     * @throws IpRestException
     * @throws Exception
     */
    public function displayAPI(): array
    {
        /** @var IpRestDeskInterface $connector */
        $connector = $this->getMyConnecteur();
        return $connector->getDeskList();
    }
}
