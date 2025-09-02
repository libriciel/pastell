<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5\Model;

final class SignificantProperties
{
    public const string TYPE = 'i_Parapheur_reserved_type';
    public const string SUBTYPE = 'i_Parapheur_reserved_subtype';
    public const string MAIN_DOCUMENT = 'i_Parapheur_reserved_mainDocument';
    public const string DUE_DATE = 'i_Parapheur_reserved_dueDate';
    public const string TRUE = 'true';
    public const string FALSE = 'false';
    public string $significantPropertiesType;
    public string $significantPropertiesValue;
}
