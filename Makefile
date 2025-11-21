DOCKER=docker
DOCKER_COMPOSE=docker compose \
-f docker/compose.yaml \
-f docker/compose.dev.yaml \
$(if $(wildcard docker/compose.override.yaml),-f docker/compose.override.yaml)

MAKE_MODULE=$(DOCKER_COMPOSE_EXEC) bin/console app:studio:make-module
EXEC_TRIVY=$(DOCKER) run -it --rm -v /var/run/docker.sock:/var/run/docker.sock -v ~/.cache:/root/.cache -v ${PWD}/.trivyignore:/.trivyignore aquasec/trivy image --severity HIGH,CRITICAL pastell-local-dev

IN_CONTAINER := $(shell [ -f /.dockerenv ] && echo 1 || echo 0)
.DEFAULT_GOAL := help
.PHONY: help

ifneq ($(IN_CONTAINER),1)
    DOCKER_COMPOSE_EXEC=$(DOCKER_COMPOSE) exec app
    DOCKER_COMPOSE_RUN=$(DOCKER_COMPOSE) run --rm --entrypoint /bin/sh app
    DOCKER_COMPOSE_UP=$(DOCKER_COMPOSE) up -d
else
	DOCKER_COMPOSE_EXEC=
	DOCKER_COMPOSE_RUN=
	DOCKER_COMPOSE_UP=
endif

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

env:
ifeq ($(wildcard docker/.env),)
	cp docker/.env.dev.example docker/.env
	sed -i "s/^DATABASE_ROOT_PASSWORD=.*/DATABASE_ROOT_PASSWORD=$(shell openssl rand -base64 32 | md5sum | cut -d' ' -f1)/" docker/.env
	sed -i "s/^DATABASE_PASSWORD=.*/DATABASE_PASSWORD=$(shell openssl rand -base64 32 | md5sum | cut -d' ' -f1)/" docker/.env
	sed -i "s/^DATABASE_PASSWORD_TEST=.*/DATABASE_PASSWORD_TEST=$(shell openssl rand -base64 32 | md5sum | cut -d' ' -f1)/" docker/.env
endif


composer-install: ## Run composer install
	$(DOCKER_COMPOSE_RUN) -c "composer install"

npm-install: ## Run npm install
	$(DOCKER_COMPOSE_RUN) -c "npm install"

install: npm-install composer-install ## Install the project NPM and PHP dependencies

clean: ## Clear and remove dependencies
	rm -f web/node_modules web-mailsec/node_modules
	rm -rf node_modules vendor

test: phpcs phpunit codeception ## Run all tests (code style, unit test, ...)

docker-compose-up: ## Up all container
	$(DOCKER_COMPOSE_UP)

phpcs: docker-compose-up ## Check code style through docker-compose
	$(DOCKER_COMPOSE_EXEC) composer phpcs

phpcbf: docker-compose-up ## Fix all code style errors
	$(DOCKER_COMPOSE_EXEC) composer phpcbf

phpunit: docker-compose-up ## Run unit test through docker-compose
	$(DOCKER_COMPOSE_EXEC) composer test

coverage: docker-compose-up ## Run unit test through docker-compsose with coverage
	$(DOCKER_COMPOSE_EXEC) composer test-cover

codeception:  ## Run acceptance tests
	$(DOCKER_COMPOSE) -f docker/compose.codeception.yaml up -d
	$(DOCKER_COMPOSE) -f docker/compose.codeception.yaml exec app composer codecept
	$(DOCKER_COMPOSE_UP)

phpstan: docker-compose-up ## Run phpstan
	$(DOCKER_COMPOSE_EXEC) vendor/bin/phpstan --xdebug

trivy: ## Run trivy
	$(EXEC_TRIVY)

start:  ## Start all services
	$(DOCKER_COMPOSE) up -d --remove-orphans

start-minio:  ## Start all services with minio
	$(DOCKER_COMPOSE) -f docker/compose.minio.yaml up -d --remove-orphans

stop: ## Stop all services
	$(DOCKER_COMPOSE) down

stop-minio:  ## Start all services with minio
	$(DOCKER_COMPOSE) -f docker/compose.minio.yaml down

module-json-actes: docker-compose-up ## Run make-module json-actes
	$(MAKE_MODULE) ./json-studio/json-actes/draft-ls-actes.json ./module/ --id ls-actes --name "Actes"
	$(MAKE_MODULE) ./json-studio/json-actes/draft-ls-actes-publication.json ./module/ --id ls-actes-publication --name "Actes publication"
	$(MAKE_MODULE) ./json-studio/json-actes/draft-ls-dossier-seance.json ./module/ --id ls-dossier-seance --name "Dossier de séance (archivage)"
	$(MAKE_MODULE) ./json-studio/json-actes/draft-ls-recup-actes-s2low.json ./module/ --id ls-recup-actes-s2low --name "Récupération arriéré Actes s2low"  --restriction_pack 'suppl_recup_actes_s2low'
	$(MAKE_MODULE) ./json-studio/json-actes/draft-ls-actes-tdt-versant-sae.json ./module/ --id ls-actes-tdt-versant-sae --name "Actes TdT versant (archivage)"  --restriction_pack 'suppl_actes_tdt_versant_sae'

module-json-document: docker-compose-up ## Run make-module json-document
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-commande.json ./module/ --id ls-commande --name "Commande"
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-commande-destinataire.json ./module/ --id ls-commande-destinataire --name "Commande (destinataire)"
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-document.json ./module/ --id ls-document --name "Document"
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-document-destinataire.json ./module/ --id ls-document-destinataire --name "Document (destinataire)"
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-document-pdf.json ./module/ --id ls-document-pdf --name "Document PDF"
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-document-pdf-destinataire.json ./module/ --id ls-document-pdf-destinataire --name "Document PDF (destinataire)"
	$(MAKE_MODULE) ./json-studio/json-document/draft-ls-recup-parapheur.json ./module/ --id ls-recup-parapheur --name "Récupération parapheur" --restriction_pack 'suppl_recup_fin_parapheur'

module-json-gfc: docker-compose-up ## Run make-module json-gfc
	$(MAKE_MODULE) ./json-studio/json-gfc/draft-gfc-dossier.json ./module/ --id gfc-dossier --name "Dossier GFC"
	$(MAKE_MODULE) ./json-studio/json-gfc/draft-gfc-dossier-destinataire.json ./module/ --id gfc-dossier-destinataire --name "Dossier GFC (destinataire)"

module-json-helios: docker-compose-up ## Run make-module json-helios
	$(MAKE_MODULE) ./json-studio/json-helios/draft-ls-helios.json ./module/ --id ls-helios --name "Helios"
	$(MAKE_MODULE) ./json-studio/json-helios/draft-ls-helios-pj.json ./module/ --id ls-helios-pj --name "Helios PES PJ"
	$(MAKE_MODULE) ./json-studio/json-helios/draft-ls-recup-pes-s2low.json ./module/ --id ls-recup-pes-s2low --name "Récupération arriéré PES s2low"  --restriction_pack 'suppl_recup_pes_s2low'
	$(MAKE_MODULE) ./json-studio/json-helios/draft-ls-helios-tdt-versant-sae.json ./module/ --id ls-helios-tdt-versant-sae --name "Helios TdT versant (archivage)"  --restriction_pack 'suppl_helios_tdt_versant_sae'

module-json-mailsec: docker-compose-up ## Run make-module json-mailsec
	$(MAKE_MODULE) ./json-studio/json-mailsec/draft-ls-mailsec.json ./module/ --id ls-mailsec --name "Mail sécurisé"
	$(MAKE_MODULE) ./json-studio/json-mailsec/draft-ls-mailsec-destinataire.json ./module/ --id ls-mailsec-destinataire --name "Mail sécurisé (destinataire)"
	$(MAKE_MODULE) ./json-studio/json-mailsec/draft-ls-mailsec-bidir.json ./module/ --id ls-mailsec-bidir --name "Mail sécurisé avec réponse"
	$(MAKE_MODULE) ./json-studio/json-mailsec/draft-ls-mailsec-bidir-destinataire.json ./module/ --id ls-mailsec-bidir-destinataire --name "Mail sécurisé avec réponse (destinataire)"
	$(MAKE_MODULE) ./json-studio/json-mailsec/draft-ls-mailsec-bidir-reponse.json ./module/ --id ls-mailsec-bidir-reponse --name "Mail sécurisé avec réponse (réponse)"

module-json-rh: docker-compose-up ## Run make-module json-rh
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-archivage-collectif.json ./module/ --id rh-archivage-collectif --name "Données de gestion collective (fichier unitaire) (archivage)" --restriction_pack 'pack_rh'
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-archivage-collectif-zip.json ./module/ --id rh-archivage-collectif-zip --name "Données de gestion collective (fichier compressé) (archivage)" --restriction_pack 'pack_rh'
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-archivage-dossier-agent.json ./module/ --id rh-archivage-dossier-agent --name "Eléments du dossier individuel de l'agent (archivage)" --restriction_pack 'pack_rh'
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-bulletin-salaire.json ./module/ --id rh-bulletin-salaire --name "Bulletin de salaire" --restriction_pack 'pack_rh'
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-bulletin-salaire-destinataire.json ./module/ --id rh-bulletin-salaire-destinataire --name "Bulletin de salaire (destinataire)" --restriction_pack 'pack_rh'
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-document-individuel.json ./module/ --id rh-document-individuel --name "Document individuel" --restriction_pack 'pack_rh'
	$(MAKE_MODULE) ./json-studio/json-rh/draft-rh-document-individuel-destinataire.json ./module/ --id rh-document-individuel-destinataire --name "Document individuel (destinataire)" --restriction_pack 'pack_rh'

module-json-urbanisme: docker-compose-up ## Run make-module json-urbanisme
	$(MAKE_MODULE) ./json-studio/json-urbanisme/draft-document-autorisation-urbanisme.json ./module/ --id document-autorisation-urbanisme --name "Document d'autorisation d'urbanisme" --restriction_pack 'pack_urbanisme'
	$(MAKE_MODULE) ./json-studio/json-urbanisme/draft-document-autorisation-urbanisme-destinataire.json ./module/ --id document-autorisation-urbanisme-destinataire --name "Document d'autorisation d'urbanisme (destinataire)" --restriction_pack 'pack_urbanisme'
	$(MAKE_MODULE) ./json-studio/json-urbanisme/draft-dossier-autorisation-urbanisme.json ./module/ --id dossier-autorisation-urbanisme --name "Dossier d'autorisation d'urbanisme (archivage)" --restriction_pack 'pack_urbanisme'

all-module: module-json-actes module-json-document module-json-gfc module-json-helios module-json-mailsec module-json-rh module-json-urbanisme

build-app: ## Build app container
	$(DOCKER_COMPOSE) build app

build-web: ## Build web container
	$(DOCKER_COMPOSE) build web

build: build-app build-web ## Build containers

bash: docker-compose-up ## Get a bash console
	$(DOCKER_COMPOSE) exec app bash

logs: docker-compose-up ## Display last application logs (in follow mode)
	$(DOCKER_COMPOSE) logs -f --tail 50 app
