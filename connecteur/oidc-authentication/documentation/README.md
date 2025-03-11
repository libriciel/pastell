# Docker keycloak

Voir `compose.yaml` et `keycloak_pastell.json`

# Configuration du connecteur

* Provider url (url avant le `.well-known/openid-configuration`):  http://keycloak.tld:port/realms/pastell/
* client id :  `account`
* client secret : `132ff39b-eedd-4fa6-993d-a3ffe63b6b0b`. Sur keycloak : Menu Clients > choisir le client id > onglet Credentials
* Attribut pour le login : `preferred_username` pour keycloak

# Users

## Administrateur keycloak (realm master)

* admin:admin

# realm pastell

* admin:admin
* user:user
* user1:user1
* user2:user2
* user3:user3

# Erreurs possibles

## Invalid parameter: redirect_uri

Cela veut dire que l'URL du pastell n'est pas autorisée par le client keycloak utilisé.
Il faut se rendre dans le menu Clients > choisir le client id et ajouter l'url dans le champ "Valid Redirect URIs" : https://pastell.tld/Connexion/oidc

Pastell utilisera toujours `/Connexion/oidc` comme URL de redirection à l'exception du test dans le connecteur.

Lors de l'utilisation de ce test, l'URL de redirection est `/Connexion/externalAuthentication?id_ce=<id_ce>`.
`<id_ce>` étant le numéro du connecteur.
