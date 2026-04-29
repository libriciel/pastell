<?php

declare(strict_types=1);

namespace Pastell\Bootstrap;

use ConfigurationSQL;

class SystemConfiguration implements InstallableBootstrap
{
    public function __construct(
        private readonly ConfigurationSQL $configurationSQL,
        private readonly array $admin_email,
        private readonly string $libelle_plateforme_mail,
        private readonly int $workspace_alert_threshold_default,
    ) {
    }

    public function install(): InstallResult
    {
        if (!$this->configurationSQL->hasConfiguration(ConfigurationSQL::ADMIN_EMAIL, ConfigurationSQL::NULL_ID_E)) {
            $this->configurationSQL->setAdminEmails($this->admin_email);
        }
        if (!$this->configurationSQL->hasConfiguration(ConfigurationSQL::LIBELLE_PLATEFORME_MAIL, ConfigurationSQL::NULL_ID_E)) {
            $this->configurationSQL->setConfiguration(
                ConfigurationSQL::LIBELLE_PLATEFORME_MAIL,
                $this->libelle_plateforme_mail,
                ConfigurationSQL::NULL_ID_E
            );
        }
        if (!$this->configurationSQL->hasConfiguration(ConfigurationSQL::WORKSPACE_ALERT_THRESHOLD, ConfigurationSQL::NULL_ID_E)) {
            $this->configurationSQL->setWorkspaceAlertThreshold($this->workspace_alert_threshold_default);
        }
        return InstallResult::InstallOk;
    }

    public function getName(): string
    {
        return 'System configuration';
    }
}
