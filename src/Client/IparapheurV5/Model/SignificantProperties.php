<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

final class SignificantProperties
{
    public const  TYPE = 'i_Parapheur_reserved_type';
    public const  SUBTYPE = 'i_Parapheur_reserved_subtype';
    public const  MAIN_DOCUMENT = 'i_Parapheur_reserved_mainDocument';
    public string $significantPropertiesType;
    public string $significantPropertiesValue;
}
