<?php

abstract class AuthenticationConnecteur extends Connecteur
{
    public const string CONNECTEUR_TYPE_ID = 'Authentification';

    abstract public function testAuthenticate(string $redirectUrl);

    abstract public function authenticate($redirectUrl = false);

    abstract public function logout($redirectUrl = false);

    abstract public function getExternalSystemName(): string;

    abstract public function getLogoutRedirectUrl(): string;
}
