[![Minimum PHP Version](http://img.shields.io/badge/php-%207.2-8892BF.svg)](https://php.net/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![pipeline status](https://gitlab.libriciel.fr/pastell/pastell/badges/master/pipeline.svg)](https://gitlab.libriciel.fr/pastell/pastell/commits/master)
[![coverage report](https://gitlab.libriciel.fr/pastell/pastell/badges/master/coverage.svg)](https://gitlab.libriciel.fr/pastell/pastell/commits/master)
[![Lignes de code](https://sonarqube.libriciel.fr/api/project_badges/measure?project=pastell&metric=ncloc)](https://sonarqube.libriciel.fr/dashboard?id=pastell)
[![Alerte](https://sonarqube.libriciel.fr/api/project_badges/measure?project=pastell&metric=alert_status)](https://sonarqube.libriciel.fr/dashboard?id=pastell)
[![Dette Technique](https://sonarqube.libriciel.fr/api/project_badges/measure?project=pastell&metric=sqale_index)](https://sonarqube.libriciel.fr/dashboard?id=pastell)

# Pastell

Plate-forme Adullact Sécurisée Transactionnelle d’Échanges en Logiciel Libre

Pastell est une solution libre et sécurisée, développée pour permettre le traitement sécurisé, automatisé et tracé de
l'ensemble des process dématérialisés.

# Démarrage en mode dev

Prérequis :
- docker
- docker compose

```shell
make env
```
Adapter le fichier `docker/.env` si nécessaire

Si la configuration du fichier `docker/compose.dev.yaml` contient des données à surcharger, il est nécessaire de créer
un fichier `docker/compose.override.yaml`.

Créer les répertoires et donner les bonnes permissions (à adapter si configuration différente d'origine) :

```shell
mkdir -p /data/pastell/app/{log,sessions,tmp,upload_chunk,workspace}
chown 1000:1000 -R /data/pastell/app

mkdir -p /data/pastell/web/{certificates,letsencrypt,logs}
chown 82:82 -R /data/pastell/web
```

```shell
make build
make install
make start
```

Le mot de passe généré de l'administrateur peut être récupéré dans le fichier `pastell.log` de `$APP_LOGS_PATH`

Accès au site : https://localhost:8443

# Utilisation de l'API Pastell

Pour utiliser l'API de Pastell en PHP, on pourra utiliser le package pastell-api-php

```
composer require libriciel/pastell-api-php
```
