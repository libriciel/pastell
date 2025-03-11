<?php

use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;
use Pastell\Security\Authentication\OpenIDConnectClientFactory;

final class OidcAuthentication extends AuthenticationConnecteur
{
    private const OIDC_REDIRECT_URI = '/Connexion/oidc';
    private const OIDC_ID_TOKEN = 'oidc_id_token';

    private string $loginAttribute;
    private string $givenNameAttribute;
    private string $familyNameAttribute;
    private string $emailAttribute;
    private bool $userCreation;

    private OpenIDConnectClient $oidc;
    private OpenIDConnectClientFactory $openIDConnectClientFactory;

    private string $logoutRedirectUrl;

    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
        private readonly RoleUtilisateur $roleUtilisateur,
        private readonly string $site_base,
    ) {
        $this->setOpenIDConnectClientFactory(new OpenIDConnectClientFactory());
    }

    public function setOpenIDConnectClientFactory(OpenIDConnectClientFactory $openIDConnectClientFactory): void
    {
        $this->openIDConnectClientFactory = $openIDConnectClientFactory;
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $providerUrl = $donneesFormulaire->get('provider_url');
        $clientId = $donneesFormulaire->get('client_id');
        $clientSecret = $donneesFormulaire->get('client_secret');
        $this->loginAttribute = $donneesFormulaire->get('login_attribute');
        $this->givenNameAttribute = $donneesFormulaire->get('given_name_attribute');
        $this->familyNameAttribute = $donneesFormulaire->get('family_name_attribute');
        $this->emailAttribute = $donneesFormulaire->get('email_attribute');
        $this->logoutRedirectUrl = $donneesFormulaire->get('redirect_url', '');
        $this->userCreation = (bool)$donneesFormulaire->get('user_creation');
        $this->oidc = $this->openIDConnectClientFactory->getInstance(
            $providerUrl,
            $clientId,
            $clientSecret
        );

        if ($donneesFormulaire->get('scopes')) {
            $this->oidc->addScope(
                \explode(
                    ',',
                    \str_replace(' ', '', $donneesFormulaire->get('scopes'))
                )
            );
        }
        if ($donneesFormulaire->get('http_proxy')) {
            $this->oidc->setHttpProxy($donneesFormulaire->get('http_proxy'));
        }
    }

    /**
     * @return mixed
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function authenticate($redirectUrl = false)
    {
        $userInfo = $this->getConnectedUserInfo(\rtrim($this->site_base, '/') . self::OIDC_REDIRECT_URI);
        if (empty($userInfo[$this->loginAttribute])) {
            throw new UnrecoverableException(sprintf(
                "L'attribut %s utilisé pour le login n'a pas été trouvé " .
                "sur la réponse du serveur d'authentification OpenID connect",
                $this->loginAttribute
            ));
        }
        if (empty($userInfo[$this->loginAttribute])) {
            throw new UnrecoverableException("Le champs login n'a pas été trouvé dans la réponse OpenID Connect");
        }

        if ($this->userCreation) {
            $this->createUserIfNeeded($userInfo);
        }
        return $userInfo[$this->loginAttribute];
    }

    /**
     * @throws OpenIDConnectClientException
     */
    public function testAuthenticate(string $redirectUrl)
    {
        $this->oidc->setRedirectURL($redirectUrl);
        $this->oidc->authenticate();
        $_SESSION[self::OIDC_ID_TOKEN] = $this->oidc->getIdToken();
        return $this->oidc->requestUserInfo($this->loginAttribute);
    }

    /**
     * @throws OpenIDConnectClientException
     * @throws JsonException
     */
    public function getConnectedUserInfo($redirectUrl = false): array
    {
        if ($redirectUrl) {
            $this->oidc->setRedirectURL($redirectUrl);
        }

        $this->oidc->authenticate();
        $_SESSION[self::OIDC_ID_TOKEN] = $this->oidc->getIdToken();
        return json_decode(
            json_encode($this->oidc->requestUserInfo(), JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @throws UnrecoverableException
     * @throws Exception
     */
    private function createUserIfNeeded(array $userInfo): void
    {
        $login = $userInfo[$this->loginAttribute];
        if ($this->utilisateurSQL->getIdFromLogin($login) !== false) {
            return;
        }
        $email = $userInfo[$this->emailAttribute] ?? '';
        $id_u = $this->utilisateurSQL->create($login, random_int(1, mt_getrandmax()), $email, '');
        $this->utilisateurSQL->setNomPrenom(
            $id_u,
            $userInfo[$this->familyNameAttribute] ?? '',
            $userInfo[$this->givenNameAttribute] ?? ''
        );
        $this->utilisateurSQL->validMailAuto($id_u);
        $this->roleUtilisateur->addRole($id_u, RoleUtilisateur::AUCUN_DROIT, 0);
    }

    public function logout($redirectUrl = false)
    {
        $this->oidc->signOut($_SESSION[self::OIDC_ID_TOKEN], $redirectUrl ?: $this->site_base);
    }

    public function getExternalSystemName(): string
    {
        return 'OIDC';
    }

    public function getLogoutRedirectUrl(): string
    {
        return $this->logoutRedirectUrl;
    }
}
