<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use DictionnaryChoice;
use Exception;
use IparapheurRest;
use Pastell\Connector\IparapheurRest\IpRestException;

class IpRestGetTypeList extends DictionnaryChoice
{
    public function getElementId(): string
    {
        return 'iparapheur_type_id';
    }

    public function getElementName(): string
    {
        return 'iparapheur_type';
    }

    public function getTitle(): string
    {
        return 'Sélectionner un type';
    }

    /**
     * @throws IpRestException
     * @throws Exception
     */
    public function displayAPI(): array
    {
        /** @var IparapheurRest $connector */
        $connector = $this->getMyConnecteur();
        return $connector->getTypeList();
    }
}
