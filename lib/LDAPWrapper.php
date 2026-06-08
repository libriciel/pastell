<?php

use LDAP\Connection;
use LDAP\Result;

class LDAPWrapper
{
    /**
     * @param Connection $link_identifier
     */
    public function ldap_bind(
        $link_identifier,
        ?string $bind_rdn = null,
        ?string $bind_password = null
    ): bool {
        return ldap_bind($link_identifier, $bind_rdn, $bind_password);
    }

    public function ldap_connect(?string $hostname = null, int $port = 389): bool|Connection
    {
        if ($hostname === null) {
            return ldap_connect();
        }
        $uri = preg_match('#^ldaps?://#', $hostname) ? $hostname : "ldap://$hostname:$port";
        return ldap_connect($uri);
    }

    /**
     * @param Connection $link_identifier
     */
    public function ldap_error($link_identifier): ?string
    {
        return ldap_error($link_identifier);
    }

    /**
     * @param Connection $ldap_identifier
     */
    public function ldap_set_option($ldap_identifier, int $option, mixed $newval): bool
    {
        return ldap_set_option($ldap_identifier, $option, $newval);
    }

    /**
     * @param Connection $link_identifier
     */
    public function ldap_search(
        $link_identifier,
        string $base_dn,
        string $filter,
        array $attributes = [],
        int $attrsonly = 0,
        int $sizelimit = -1,
        int $timelimit = -1,
        int $deref = 0
    ): Result|array|false {
        return ldap_search(
            $link_identifier,
            $base_dn,
            $filter,
            $attributes,
            $attrsonly,
            $sizelimit,
            $timelimit,
            $deref
        );
    }

    /**
     * @param Connection $link_identifier
     * @param Result $ressource
     */
    public function ldap_count_entries($link_identifier, $ressource): int
    {
        return ldap_count_entries($link_identifier, $ressource);
    }

    /**
     * @param Connection $link_identifier
     * @param Result $result_identifier
     */
    public function ldap_get_entries($link_identifier, $result_identifier): array|false
    {
        return ldap_get_entries($link_identifier, $result_identifier);
    }
}
