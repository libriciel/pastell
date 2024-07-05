<?php

namespace Pastell\Client\IparapheurV5\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

class PremisObject
{
    #[SerializedName('@xsi:type')]
    public string $type;

    public ObjectIdentifier $objectIdentifier;

    /** @var SignificantProperties[] $significantProperties */
    public array $significantProperties;

    public string $originalName;
}
