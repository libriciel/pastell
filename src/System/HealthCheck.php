<?php

namespace Pastell\System;

use ObjectInstancier;
use UnrecoverableException;

class HealthCheck
{
    public const WORKSPACE_CHECK = 'WORKSPACE_CHECK';
    public const JOURNAL_CHECK = 'JOURNAL_CHECK';
    public const REDIS_CHECK = 'REDIS_CHECK';
    public const PHP_CONFIGURATION_CHECK = 'PHP_CONFIGURATION_CHECK';
    public const PHP_EXTENSIONS_CHECK = 'PHP_EXTENSIONS_CHECK';
    public const EXPECTED_ELEMENTS_CHECK = 'EXPECTED_ELEMENTS_CHECK';
    public const COMMAND_CHECK = 'COMMAND_CHECK';
    public const CONSTANTS_CHECK = 'CONSTANTS_CHECK';
    public const DATETIME_CHECK = 'DATETIME_CHECK';
    public const DATABASE_SCHEMA_CHECK = 'DATABASE_SCHEMA_CHECK';
    public const DATABASE_ENCODING_CHECK = 'DATABASE_ENCODING_CHECK';
    public const CRASHED_TABLES_CHECK = 'CRASHED_TABLES_CHECK';
    public const DAEMON_CHECK = 'DAEMON_CHECK';
    public const MISSING_CONNECTORS_CHECK = 'MISSING_CONNECTORS_CHECK';
    public const MISSING_MODULES_CHECK = 'MISSING_MODULES_CHECK';
    public const PACK_CHECK = 'PACK_CHECK';

    /**
     * @var ObjectInstancier
     */
    private $objectInstancier;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
    }

    public function getSubscribedChecks(): array
    {
        return [
            self::WORKSPACE_CHECK => Check\WorkspaceCheck::class,
            self::JOURNAL_CHECK => Check\JournalCheck::class,
            self::REDIS_CHECK => Check\RedisCheck::class,
            self::PHP_CONFIGURATION_CHECK => Check\PhpConfigurationCheck::class,
            self::PHP_EXTENSIONS_CHECK => Check\PhpExtensionsCheck::class,
            self::EXPECTED_ELEMENTS_CHECK => Check\ExpectedElementsCheck::class,
            self::COMMAND_CHECK => Check\CommandCheck::class,
            self::CONSTANTS_CHECK => Check\ConstantsCheck::class,
            self::DATABASE_SCHEMA_CHECK => Check\DatabaseSchemaCheck::class,
            self::DATABASE_ENCODING_CHECK => Check\DatabaseEncodingCheck::class,
            self::CRASHED_TABLES_CHECK => Check\CrashedTablesCheck::class,
            self::DAEMON_CHECK => Check\DaemonCheck::class,
            self::MISSING_CONNECTORS_CHECK => Check\MissingConnectorsCheck::class,
            self::MISSING_MODULES_CHECK => Check\MissingModulesCheck::class,
            self::DATETIME_CHECK => Check\DatetimeCheck::class,
            self::PACK_CHECK => Check\PackCheck::class
        ];
    }

    /**
     * @return HealthCheckItem[]
     * @throws UnrecoverableException
     */
    public function check(string $check): array
    {
        $checks = $this->getSubscribedChecks();
        if (empty($checks[$check])) {
            throw new UnrecoverableException("La vérification $check n'existe pas");
        }
        /** @var CheckInterface $object */
        $object = $this->objectInstancier->getInstance($checks[$check]);
        return $object->check();
    }
}
