<?php

declare(strict_types=1);

namespace Pastell\Client\IparapheurV5;

class IparapheurAuthConfig
{
    public string $keycloakUsername;
    public string $keycloakPassword;
    public string $iparapheurUrl;
    public const KEYCLOAK_CLIENT_ID = 'ipcore-web';
    public string $keycloakClientId;
    public const KEYCLOAK_URL = '/auth/realms/api/protocol/openid-connect/token';
    public string $keycloakUrl;

    public function __construct(
        string $keycloakUsername,
        string $keycloakPassword,
        string $iparapheurUrl
    ) {
        $this->keycloakUsername = $keycloakUsername;
        $this->keycloakPassword = $keycloakPassword;
        $this->keycloakClientId = self::KEYCLOAK_CLIENT_ID;
        $this->iparapheurUrl = $iparapheurUrl;
        $this->keycloakUrl = $iparapheurUrl . self::KEYCLOAK_URL;
    }
}
