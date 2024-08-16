[![Minimum PHP Version](http://img.shields.io/badge/php-%207.2-8892BF.svg)](https://php.net/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![pipeline status](https://gitlab.libriciel.fr/pastell/pastell/badges/master/pipeline.svg)](https://gitlab.libriciel.fr/pastell/pastell/commits/master)
[![coverage report](https://gitlab.libriciel.fr/pastell/pastell/badges/master/coverage.svg)](https://gitlab.libriciel.fr/pastell/pastell/commits/master)
[![Lignes de code](https://sonarqube.libriciel.fr/api/project_badges/measure?project=pastell&metric=ncloc)](https://sonarqube.libriciel.fr/dashboard?id=pastell)
[![Alerte](https://sonarqube.libriciel.fr/api/project_badges/measure?project=pastell&metric=alert_status)](https://sonarqube.libriciel.fr/dashboard?id=pastell)
[![Dette Technique](https://sonarqube.libriciel.fr/api/project_badges/measure?project=pastell&metric=sqale_index)](https://sonarqube.libriciel.fr/dashboard?id=pastell)

# Pastell

Plate-forme Adullact Sécurisée Transactionnelle d’Échanges en Logiciel Libre

Pastell est une solution libre et sécurisée, développée pour permettre le traitement sécurisé, automatisé et tracé de l'ensemble des process dématérialisés.

# TL;DR

Il est nécessaire d'avoir docker et docker-compose sur l'environnement de développement

```bash
make install
make start
```

Sur `https://localhost:8443/` il est possible de se connecter avec le login `admin` et le mot de passe `admin`


# Utilisation avec docker-compose

Le conteneur Pastell est basé sur [ubuntu:18.04](https://hub.docker.com/_/ubuntu/).

Lors du démarrage, le conteneur :
- crée ou met à jour la base de données ;
- crée un utilisateur admin si celui-ci n'existe pas ; 
- lance le démon Pastell.
 
### Variables d'environnement, introduites dans le docker-compose

| Variable d'environnement | Signification                                                                          | Valeur par défaut                                    |
|----|----------------------------------------------------------------------------------------|------------------------------------------------------|
| PASTELL_SITE_BASE | URL de base d'accès au site                                                            | http://localhost:8443/                               |
| MYSQL_USER | Login de l'utilisateur ayant accès à la base Pastell                                   | user                                                 |
| MYSQL_PASSWORD | Mot de passe de cet utilisateur                                                        | user                                                 |
| MYSQL_HOST | Hôte de la base de données                                                             | localhost                                            
| MYSQL_PORT | Port de connexion                                                                      | 3306                                                 |
| MYSQL_DATABASE | Nom de la base de données                                                              | pastell                                              |
| REDIS_SERVER | Hôte du serveur Redis                                                                  | (vide)                                               |
| REDIS_PORT | Port du serveur Redis                                                                  | 6379                                                 |
| PASTELL_ADMIN_LOGIN | Login de l'administrateur                                                              | admin                                                |
| PASTELL_ADMIN_EMAIL | Email de l'administrateur                                                              | test@libriciel.invalid                               |
| PASTELL_EXTENSION_PATH | Chemin vers les extensions Pastell                                                     | `..` (répertoire qui contient le répertoire pastell) |
| WORKSPACE_VOLUME | Chemin vers le workspace Pastell                                                       | création du volume nommé app_workspace               |
| PASTELL_SSL_CERTIFICAT | Chemin vers les certificats (site web, `validca`, ...)                                   | création du volume nommé app_certificate             |
| PASTELL_SESSION | Chemin vers les sessions PHP                                                           | création du volume nommé app_session                 |


## Utilisation de l'environnement de développement et de test via docker-compose


Ajouter les variables suivantes : 

| Variable d'environnement | Signification | Valeur par défaut |
| ----| ---- | ---- |
| MYSQL_HOST_TEST | Mot de passe root de la base de données (de test) | localhost |
| MYSQL_DATABASE_TEST | Nom de la base de test | pastell_test |
| MYSQL_USER_TEST | Login de l'utilisateur ayant accès à la base Pastell de test | user |
| MYSQL_PASSWORD_TEST | Mot de passe de cet utilisateur | user |


Démarrage : 
```
docker-compose up -d
```

- Accès au site : http://localhost:8000
- Accès à phpMyAdmin : http://localhost:8001 
- Accès au site de test (pour codeception) : http://localhost:8003
- Accès à la base de données : mysql -u user -p pastell -h localhost -P8306

## Utilisation via PHPStorm

PHPStorm n'utilise pas docker-compose et écrase la commande `entrypoint` lors du lancement de PHPUnit, 
il convient donc de spécifier les variables d'environnements directement dans la configuration du lanceur PHPUnit.


## Utilisation via gitlab-ci

Gitlab-ci utilise la commande `entrypoint` mais surcharge la commande.

# Utilisation de l'API Pastell

Pour utiliser l'API de Pastell en PHP, on pourra utiliser le package pastell-api-php

```
composer require libriciel/pastell-api-php
```
