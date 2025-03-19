<?php

declare(strict_types=1);

namespace Pastell\Connector\IparapheurRest;

interface IpRestDeskInterface
{
    public function getDeskList(): array;
}
