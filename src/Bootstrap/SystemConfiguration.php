<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use ConfigurationSQL;

class SystemConfiguration implements InstallableBootstrap
{
    public const string ADMIN_EMAIL = 'ADMIN_EMAIL';
    public function __construct(
        private readonly ConfigurationSQL $configurationSQL,
        private readonly array $admin_email,
    ) {
    }

    public function install(): InstallResult
    {
        if (!$this->configurationSQL->hasConfiguration(self::ADMIN_EMAIL, ConfigurationSQL::NULL_ID_E)) {
            $this->configurationSQL->setConfiguration(
                self::ADMIN_EMAIL,
                implode(',', $this->admin_email),
                ConfigurationSQL::NULL_ID_E
            );
        }
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'System configuration';
    }
}
