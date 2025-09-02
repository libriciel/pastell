<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest\Action;

use DictionnaryChoice;
use Exception;
use Pastell\Connector\IparapheurRest\IpRestTenantInterface;

class IpRestGetTenantList extends DictionnaryChoice
{
    public function getElementId(): string
    {
        return 'tenant_id';
    }

    public function getElementName(): string
    {
        return 'tenant_name';
    }

    public function getTitle(): string
    {
        return 'Sélectionner une entité';
    }

    /**
     * @throws Exception
     */
    public function displayAPI(): array
    {
        /** @var IpRestTenantInterface $connector */
        $connector = $this->getMyConnecteur();
        return $connector->getTenantList();
    }
}
