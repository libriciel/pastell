<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use ConfigurationSQL;

class SystemConfiguration implements InstallableBootstrap
{
    public function __construct(
        private readonly ConfigurationSQL $configurationSQL,
        private readonly array $admin_email,
    ) {
    }

    public function install(): InstallResult
    {
        if (!$this->configurationSQL->hasConfiguration(ConfigurationSQL::ADMIN_EMAIL, ConfigurationSQL::NULL_ID_E)) {
            $this->configurationSQL->setAdminEmails($this->admin_email);
        }
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'System configuration';
    }
}
