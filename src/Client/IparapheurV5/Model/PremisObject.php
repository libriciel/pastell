<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

use Symfony\Component\Serializer\Annotation\SerializedName;

class PremisObject
{
    public const string INTELLECTUAL_ENTITY = 'intellectualEntity';
    public const string FILE = 'file';

    #[SerializedName('@xsi:type')]
    public string $type;
    public ObjectIdentifier $objectIdentifier;

    /** @var SignificantProperties[] */
    public array $significantProperties;
    public ObjectCharacteristics $objectCharacteristics;
    public string $originalName;
    public SignatureInformation $signatureInformation;
}
