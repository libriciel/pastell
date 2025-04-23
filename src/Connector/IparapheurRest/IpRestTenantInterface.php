<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest;

interface IpRestTenantInterface
{
    public function getTenantList(): array;
}
