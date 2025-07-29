<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

final class SignificantProperties
{
    public const TYPE = 'i_Parapheur_reserved_type';
    public const SUBTYPE = 'i_Parapheur_reserved_subtype';
    public const MAIN_DOCUMENT = 'i_Parapheur_reserved_mainDocument';
    public const DUE_DATE = 'i_Parapheur_reserved_dueDate';
    public const TRUE = 'true';
    public const FALSE = 'false';
    public string $significantPropertiesType;
    public string $significantPropertiesValue;
}
