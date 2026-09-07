<?php

use LDAP\Connection;

class LDAPVerification extends Connecteur
{
    private const DEFAULT_HOST = "localhost";
    private const DEFAULT_PORT = 389;

    private const DEFAULT_LOGIN_ATTRIBUTE = 'uid';
    private const DEFAULT_FIRSTNAME_ATTRIBUTE = 'givenname';
    private const DEFAULT_LASTNAME_ATTRIBUTE = 'sn';
    private const DEFAULT_EMAIL_ATTRIBUTE = 'mail';

    private $ldap_host;
    private $ldap_port;
    private $ldap_user;
    private $ldap_password;
    private $ldap_filter;
    private $ldap_root;
    private string $ldap_login_attribute;
    private string $ldap_lastname_attribute = 'sn';
    private string $ldap_firstname_attribute = 'givenname';
    private string $ldap_email_attribute = 'mail';
    private $ldapWrapper;

    public function __construct(LDAPWrapper $ldapWrapper)
    {
        $this->ldapWrapper = $ldapWrapper;
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire): void
    {
        $this->ldap_host = $donneesFormulaire->get('ldap_host') ?: self::DEFAULT_HOST;
        $this->ldap_port = intval($donneesFormulaire->get('ldap_port')) ?: self::DEFAULT_PORT;
        $this->ldap_user = $donneesFormulaire->get('ldap_user');
        $this->ldap_password = $donneesFormulaire->get('ldap_password');
        $this->ldap_filter = $donneesFormulaire->get('ldap_filter');
        $this->ldap_root = $donneesFormulaire->get('ldap_root');
        $this->ldap_login_attribute = $donneesFormulaire->get('ldap_login_attribute') ?: self::DEFAULT_LOGIN_ATTRIBUTE;
        $this->ldap_lastname_attribute = $donneesFormulaire->get('ldap_lastname_attribute') ?: self::DEFAULT_LASTNAME_ATTRIBUTE;
        $this->ldap_firstname_attribute = $donneesFormulaire->get('ldap_firstname_attribute') ?: self::DEFAULT_FIRSTNAME_ATTRIBUTE;
        $this->ldap_email_attribute = $donneesFormulaire->get('ldap_email_attribute') ?: self::DEFAULT_EMAIL_ATTRIBUTE;
    }

    /**
     * @return Connection
     * @throws UnrecoverableException
     */
    public function getConnexion()
    {
        $ldap = $this->getConnexionObject();
        if (!@ $this->ldapWrapper->ldap_bind($ldap, $this->ldap_user, $this->ldap_password)) {
            throw new UnrecoverableException(
                "Impossible de s'authentifier sur le serveur LDAP : " . $this->ldapWrapper->ldap_error($ldap)
            );
        }
        return $ldap;
    }

    /**
     * @return Connection
     * @throws UnrecoverableException
     */
    private function getConnexionObject()
    {
        $ldap = $this->ldapWrapper->ldap_connect($this->ldap_host, $this->ldap_port);
        if (!$ldap) {
            throw new UnrecoverableException(
                "Impossible de se connecter sur le serveur LDAP : " . $this->ldapWrapper->ldap_error($ldap)
            );
        }
        $this->ldapWrapper->ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        $this->ldapWrapper->ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
        return $ldap;
    }

    /**
     * @param $user_id
     * @return array|bool
     * @throws UnrecoverableException
     */
    public function getEntry($user_id)
    {
        $ldap = $this->getConnexion();
        $filter = $this->ldap_filter;
        if (!$filter) {
            $filter = "(objectClass=*)";
        }
        if (!preg_match('#^\(.*\)$#', $filter)) {
            $filter = "($filter)";
        }
        $filter = "(&$filter($this->ldap_login_attribute=$user_id))";

        $result = $this->ldapWrapper->ldap_search($ldap, $this->ldap_root, $filter);
        if (!$result || $this->ldapWrapper->ldap_count_entries($ldap, $result) < 1) {
            return false;
        }
        $entries = $this->ldapWrapper->ldap_get_entries($ldap, $result);
        if (empty($entries[0]['dn'])) {
            return false;
        }
        return $entries[0]['dn'];
    }

    /**
     * @param $user_id
     * @return array|bool
     * @throws UnrecoverableException
     */
    private function getUserDN($user_id)
    {
        return $this->getEntry($user_id);
    }

    /**
     * @return array
     * @throws UnrecoverableException
     */
    public function getAllUser(): array
    {
        $ldap = $this->getConnexion();
        $dn = $this->ldap_root;
        $filter = $this->ldap_filter;
        if (!$filter) {
            $filter = "(objectClass=*)";
        }
        $result = @ $this->ldapWrapper->ldap_search(
            $ldap,
            $dn,
            $filter,
            [
                $this->ldap_login_attribute,
                $this->ldap_lastname_attribute,
                $this->ldap_email_attribute,
                $this->ldap_firstname_attribute
            ]
        );

        if ($result === false) {
            $error = $this->ldapWrapper->ldap_error($ldap);
            if ($error) {
                throw new UnrecoverableException($error);
            }
            return [];
        }
        if ($this->ldapWrapper->ldap_count_entries($ldap, $result) < 1) {
            throw new UnrecoverableException("Aucun utilisateur n'a été retourné");
        }

        return $this->ldapWrapper->ldap_get_entries($ldap, $result);
    }

    private function getAttribute($entry, $attribute_name): string
    {
        if (empty($entry[$attribute_name][0])) {
            return "";
        }
        return $entry[$attribute_name][0];
    }

    /**
     * @param UtilisateurSQL $utilisateur
     * @return array
     * @throws UnrecoverableException
     */
    public function getUserToCreate(UtilisateurSQL $utilisateur): array
    {
        $entries = $this->getAllUser();
        unset($entries['count']);
        $result = [];
        foreach ($entries as $entry) {
            $login = $this->getAttribute($entry, $this->ldap_login_attribute);
            if (!$login) {
                continue;
            }
            $email = $this->getAttribute($entry, $this->ldap_email_attribute);
            $prenom = $this->getAttribute($entry, $this->ldap_firstname_attribute);
            $nom = $this->getAttribute($entry, $this->ldap_lastname_attribute);
            $ldap_info = ['login' => $login, 'prenom' => $prenom, 'nom' => $nom, 'email' => $email];
            $id_u = $utilisateur->getIdFromLogin($login);
            if (!$id_u) {
                $ldap_info['create'] = true;
                $ldap_info['synchronize'] = true;
            } else {
                $ldap_info['create'] = false;
                $info = $utilisateur->getInfo($id_u);
                $ldap_info['id_u'] = $info['id_u'];
                $ldap_info['synchronize'] = $info['prenom'] != $prenom || $info['nom'] != $nom || $info['email'] != $email;
            }
            $result[] = $ldap_info;
        }
        return $result;
    }

    /**
     * @throws UnrecoverableException
     */
    public function verifLogin($login, $password): bool
    {
        if (!$login) {
            return false;
        }
        if (!\is_string($password) || $password === '') {
            return false;
        }
        $user_id = $this->getUserDN($login);
        if (empty($user_id)) {
            return false;
        }
        $ldap = $this->getConnexionObject();
        return @$this->ldapWrapper->ldap_bind($ldap, $user_id, $password);
    }

    /**
     * @param $login
     * @return bool
     * @throws UnrecoverableException
     */
    public function verifLDAP($login): bool
    {
        return ($this->getUserDN($login));
    }
}
