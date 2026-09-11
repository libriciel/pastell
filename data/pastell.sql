CREATE TABLE `agent` (
	`id_a` int(11) NOT NULL AUTO_INCREMENT,
	`matricule` varchar(64) NOT NULL,
	`titre` varchar(16) NOT NULL,
	`nom_usage` varchar(128) NOT NULL,
	`nom_patronymique` varchar(128) NOT NULL,
	`prenom` varchar(128) NOT NULL,
	`emploi_grade_code` varchar(16) NOT NULL,
	`emploi_grade_libelle` varchar(128) NOT NULL,
	`collectivite_code` varchar(16) NOT NULL,
	`collectivite_libelle` varchar(128) NOT NULL,
	`siren` varchar(16) NOT NULL,
	`type_dossier_code` varchar(16) NOT NULL,
	`type_dossier_libelle` varchar(128) NOT NULL,
	`train_traitement_code` varchar(16) NOT NULL,
	`train_traitement_libelle` varchar(128) NOT NULL,
	PRIMARY KEY (`id_a`),
	KEY `agent_siren_matricule` (`siren`,`matricule`)
)  ENGINE=MyISAM  ;
CREATE TABLE `annuaire` (
	`id_a` int(11) NOT NULL AUTO_INCREMENT,
	`description` varchar(64) NOT NULL,
	`email` varchar(64) NOT NULL,
	`id_e` int(11) NOT NULL,
	PRIMARY KEY (`id_a`)
)  ENGINE=MyISAM  ;
CREATE TABLE `annuaire_groupe` (
	`id_g` int(11) NOT NULL AUTO_INCREMENT,
	`id_e` int(11) NOT NULL,
	`nom` varchar(32) NOT NULL,
	`partage` tinyint(1) NOT NULL,
	PRIMARY KEY (`id_g`)
)  ENGINE=MyISAM  ;
CREATE TABLE `annuaire_groupe_contact` (
	`id_a` int(11) NOT NULL,
	`id_g` int(11) NOT NULL
)  ENGINE=MyISAM  ;
CREATE TABLE `annuaire_role` (
	`id_r` int(11) NOT NULL AUTO_INCREMENT,
	`nom` varchar(64) NOT NULL,
	`id_e_owner` int(11) NOT NULL,
	`id_e` int(11) NOT NULL,
	`role` varchar(32) NOT NULL,
	`partage` tinyint(1) NOT NULL,
	PRIMARY KEY (`id_r`)
)  ENGINE=MyISAM  ;
CREATE TABLE `configuration` (
	`config_key` varchar(32) NOT NULL,
	`config_value` varchar(128) NOT NULL,
	`id_e` int(11) NOT NULL,
	UNIQUE KEY `idx_config_key_id_e` (`config_key`,`id_e`) 
)  ENGINE=MyISAM  ;
CREATE TABLE `connecteur_action` (
	`id_a` int(11) NOT NULL AUTO_INCREMENT,
	`id_e` int(11) NOT NULL,
	`id_u` int(11) NOT NULL,
	`id_ce` int(11) NOT NULL,
	`type_dossier` varchar(64) NOT NULL,
	`action` varchar(64) NOT NULL,
	`date` datetime NOT NULL,
	`empreinte_sha256` varchar(128) NOT NULL,
	`message` mediumtext NOT NULL,
	PRIMARY KEY (`id_a`),
	KEY `id_ce` (`id_ce`)
)  ENGINE=MyISAM  ;
CREATE TABLE `connecteur_entite` (
	`id_ce` int(11) NOT NULL AUTO_INCREMENT,
	`id_e` int(11) NOT NULL,
	`libelle` varchar(128) NOT NULL,
	`id_connecteur` varchar(32) NOT NULL,
	`type` varchar(32) NOT NULL,
	`frequence_en_minute` int(11) NOT NULL DEFAULT '1',
	`id_verrou` varchar(32) NOT NULL,
	`global` tinyint(1),
	PRIMARY KEY (`id_ce`)
)  ENGINE=MyISAM  ;
CREATE TABLE `connecteur_frequence` (
	`id_cf` int(11) NOT NULL AUTO_INCREMENT,
	`type_connecteur` varchar(16) NOT NULL,
	`famille_connecteur` varchar(128) NOT NULL,
	`id_connecteur` varchar(128) NOT NULL,
	`id_ce` int(11) NOT NULL,
	`action_type` varchar(16) NOT NULL,
	`type_document` varchar(128) NOT NULL,
	`action` varchar(128) NOT NULL,
	`expression` mediumtext NOT NULL,
	`id_verrou` varchar(128) NOT NULL,
	PRIMARY KEY (`id_cf`)
)  ENGINE=MyISAM  ;
CREATE TABLE `daemon` (
	`id_daemon` int(11) NOT NULL AUTO_INCREMENT,
	`id_e` int(11),
	`state` tinyint(1) NOT NULL,
	`nb_workers` int(11) NOT NULL DEFAULT '0',
	`admin_emails` varchar(256) NOT NULL,
	`late_jobs_threshold` int(11) NOT NULL DEFAULT '1',
	PRIMARY KEY (`id_daemon`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document` (
	`id_d` varchar(64) NOT NULL,
	`type` varchar(64) NOT NULL,
	`titre` varchar(256) NOT NULL,
	`creation` datetime NOT NULL,
	`modification` datetime NOT NULL,
	PRIMARY KEY (`id_d`),
	KEY `type` (`type`),
	FULLTEXT KEY `titre` (`titre`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document_action` (
	`id_a` int(11) NOT NULL AUTO_INCREMENT,
	`id_d` varchar(64) NOT NULL,
	`action` varchar(64) NOT NULL,
	`date` datetime NOT NULL,
	`id_e` int(11) NOT NULL,
	`id_u` int(11) NOT NULL,
	PRIMARY KEY (`id_a`),
	KEY `document_action_id_d_index` (`id_d`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document_action_entite` (
	`id_a` int(11) NOT NULL,
	`id_e` int(11) NOT NULL,
	`id_j` int(11) NOT NULL,
	KEY `id_a` (`id_a`,`id_e`,`id_j`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document_email` (
	`id_de` int(11) NOT NULL AUTO_INCREMENT,
	`key` varchar(32) NOT NULL,
	`id_d` varchar(64) NOT NULL,
	`email` varchar(256) NOT NULL,
	`lu` tinyint(1) NOT NULL,
	`date_envoie` datetime NOT NULL,
	`date_lecture` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
	`type_destinataire` varchar(4) NOT NULL,
	`date_renvoi` datetime NOT NULL,
	`nb_renvoi` int(11) NOT NULL,
	`reponse` text NOT NULL,
	`has_error` tinyint(1) NOT NULL,
	`last_error` text NOT NULL,
	`non_recu` tinyint(1) NOT NULL,
	PRIMARY KEY (`id_de`),
	UNIQUE KEY `key` (`key`) ,
	KEY `document_email_id_d_index` (`id_d`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document_email_reponse` (
	`id_reponse` int(11) NOT NULL AUTO_INCREMENT,
	`id_de` int(11) NOT NULL,
	`id_d_reponse` varchar(64) NOT NULL,
	`is_lu` tinyint(1) NOT NULL DEFAULT '0',
	`has_reponse` tinyint(1) NOT NULL,
	`date_reponse` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
	`has_date_reponse` tinyint(1) NOT NULL,
	PRIMARY KEY (`id_reponse`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document_entite` (
	`id_d` varchar(64) NOT NULL,
	`id_e` int(11) NOT NULL,
	`role` varchar(16) NOT NULL,
	`last_action` varchar(64) NOT NULL,
	`last_action_date` datetime NOT NULL,
	`last_type` varchar(64) NOT NULL,
	KEY `id_e` (`id_e`,`id_d`),
	KEY `id_d` (`id_d`),
	KEY `last_action` (`last_action`),
	KEY `document_entite_count` (`id_e`,`last_type`,`last_action`)
)  ENGINE=MyISAM  ;
CREATE TABLE `document_index` (
	`id_d` varchar(64) NOT NULL,
	`field_name` varchar(64) NOT NULL,
	`field_value` varchar(128) NOT NULL,
	PRIMARY KEY (`id_d`,`field_name`),
	KEY `document_index_field_name_field_value` (`field_name`,`field_value`)
)  ENGINE=MyISAM  ;
CREATE TABLE `entite` (
	`id_e` int(11) NOT NULL AUTO_INCREMENT,
	`type` varchar(32) NOT NULL,
	`denomination` varchar(128) NOT NULL,
	`siren` char(9) NOT NULL,
	`date_inscription` datetime NOT NULL,
	`entite_mere` varchar(9),
	`centre_de_gestion` int(11) NOT NULL,
	`is_active` tinyint(1) NOT NULL DEFAULT '1',
	PRIMARY KEY (`id_e`),
	KEY `entite_mere` (`entite_mere`,`type`,`id_e`),
	KEY `denomination_2` (`denomination`),
	FULLTEXT KEY `denomination` (`denomination`)
)  ENGINE=MyISAM  ;
CREATE TABLE `entite_ancetre` (
	`id_e_ancetre` int(11) NOT NULL,
	`id_e` int(11) NOT NULL,
	`niveau` int(11) NOT NULL,
	PRIMARY KEY (`id_e`,`id_e_ancetre`),
	KEY `id_e_ancetre` (`id_e_ancetre`,`id_e`)
)  ENGINE=MyISAM  ;
CREATE TABLE `entite_properties` (
	`id_e` int(11) NOT NULL,
	`flux` varchar(16) NOT NULL,
	`properties` varchar(32) NOT NULL,
	`values` varchar(32) NOT NULL
)  ENGINE=MyISAM  ;
CREATE TABLE `extension` (
	`id_e` int(11) NOT NULL AUTO_INCREMENT,
	`nom` varchar(128) NOT NULL,
	`path` mediumtext NOT NULL,
	PRIMARY KEY (`id_e`)
)  ENGINE=MyISAM  ;
CREATE TABLE `flux_entite` (
	`id_fe` int(11) NOT NULL AUTO_INCREMENT,
	`id_e` int(11) NOT NULL,
	`flux` varchar(64) NOT NULL,
	`id_ce` int(11) NOT NULL,
	`type` varchar(32) NOT NULL,
	`num_same_type` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id_fe`),
	KEY `id_ce` (`id_ce`),
	KEY `ide_flux_type` (`id_e`,`flux`,`type`)
)  ENGINE=MyISAM  ;
CREATE TABLE `flux_entite_heritage` (
	`id_fh` int(11) NOT NULL AUTO_INCREMENT,
	`id_e` int(11) NOT NULL,
	`flux` varchar(256) NOT NULL,
	PRIMARY KEY (`id_fh`)
)  ENGINE=MyISAM  ;
CREATE TABLE `grade` (
	`libelle` varchar(256) NOT NULL,
	`filiere` varchar(255) NOT NULL,
	`cadre_emploi` varchar(255) NOT NULL
)  ENGINE=MyISAM  ;
CREATE TABLE `job_queue` (
	`id_job` int(11) NOT NULL AUTO_INCREMENT,
	`type` int(11) NOT NULL,
	`last_message` mediumtext NOT NULL,
	`lock_since` datetime NOT NULL,
	`next_try` datetime NOT NULL,
	`last_try` datetime NOT NULL,
	`id_e` int(11) NOT NULL,
	`id_d` varchar(256) NOT NULL,
	`id_u` int(11) NOT NULL,
	`id_ce` int(11) NOT NULL,
	`etat_source` varchar(256) NOT NULL,
	`etat_cible` varchar(256) NOT NULL,
	`nb_try` int(11) NOT NULL,
	`first_try` datetime NOT NULL,
	`id_verrou` varchar(32) NOT NULL,
	`id_daemon` int(11) NOT NULL,
	`job_status` tinyint(4) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id_job`)
)  ENGINE=MyISAM  ;
CREATE TABLE `journal` (
	`id_j` int(11) NOT NULL AUTO_INCREMENT,
	`type` int(11) NOT NULL,
	`id_e` int(11) NOT NULL,
	`id_u` int(11) NOT NULL,
	`id_d` varchar(64) NOT NULL,
	`action` varchar(64) NOT NULL,
	`message` mediumtext NOT NULL,
	`date` datetime NOT NULL,
	`preuve` blob NOT NULL,
	`date_horodatage` datetime NOT NULL,
	`message_horodate` mediumtext NOT NULL,
	`document_type` varchar(128) NOT NULL,
	PRIMARY KEY (`id_j`),
	KEY `id_j` (`id_u`,`id_j`),
	KEY `date` (`date`),
	KEY `id_e` (`id_e`),
	KEY `id_d` (`id_d`),
	KEY `type` (`type`),
	KEY `id_e_type_document` (`id_e`,`document_type`)
)  ENGINE=MyISAM  ;
CREATE TABLE `journal_attente_preuve` (
	`id_j` int(11) NOT NULL,
	PRIMARY KEY (`id_j`)
)  ENGINE=MyISAM  ;
CREATE TABLE `journal_historique` (
	`id_j` int(11) NOT NULL AUTO_INCREMENT,
	`type` int(11) NOT NULL,
	`id_e` int(11) NOT NULL,
	`id_u` int(11) NOT NULL,
	`id_d` varchar(64) NOT NULL,
	`action` varchar(64) NOT NULL,
	`message` mediumtext NOT NULL,
	`date` datetime NOT NULL,
	`preuve` blob NOT NULL,
	`date_horodatage` datetime NOT NULL,
	`message_horodate` mediumtext NOT NULL,
	`document_type` varchar(128) NOT NULL,
	PRIMARY KEY (`id_j`),
	KEY `id_j` (`id_u`,`id_j`),
	KEY `date` (`date`),
	KEY `id_e` (`id_e`),
	KEY `id_d` (`id_d`),
	KEY `type` (`type`)
)  ENGINE=MyISAM  ;
CREATE TABLE `magic_link` (
	`id` char(36) NOT NULL,
	`id_u` int(11) NOT NULL,
	`token` varchar(64) NOT NULL,
	`code` varchar(6) NOT NULL,
	`code_attempts` int(11) NOT NULL DEFAULT '0',
	`motif` varchar(128) NOT NULL,
	`created_by` int(11) NOT NULL,
	`created_at` datetime NOT NULL,
	`expires_at` datetime NOT NULL,
	`revoked_at` datetime,
	`user_deleted_at` datetime,
	`titulaire_nom` varchar(64) NOT NULL,
	`titulaire_prenom` varchar(64) NOT NULL,
	`titulaire_email` varchar(256) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `magic_link_token_uindex` (`token`)
)  ENGINE=MyISAM  ;
CREATE TABLE `notification` (
	`id_n` int(11) NOT NULL AUTO_INCREMENT,
	`id_u` int(11) NOT NULL,
	`id_e` int(11) NOT NULL,
	`type` varchar(64) NOT NULL,
	`action` varchar(64) NOT NULL,
	`daily_digest` tinyint(1) NOT NULL,
	PRIMARY KEY (`id_n`)
)  ENGINE=MyISAM  ;
CREATE TABLE `notification_digest` (
	`id_nd` int(11) NOT NULL AUTO_INCREMENT,
	`mail` varchar(255) NOT NULL,
	`id_e` int(11) NOT NULL,
	`id_d` varchar(64) NOT NULL,
	`action` varchar(32) NOT NULL,
	`type` varchar(64) NOT NULL,
	`message` mediumtext NOT NULL,
	PRIMARY KEY (`id_nd`)
)  ENGINE=MyISAM  ;
CREATE TABLE `role` (
	`role` varchar(64) NOT NULL,
	`libelle` varchar(255) NOT NULL,
	PRIMARY KEY (`role`)
)  ENGINE=MyISAM  ;
CREATE TABLE `role_droit` (
	`role` varchar(64) NOT NULL,
	`droit` varchar(64) NOT NULL,
	PRIMARY KEY (`role`,`droit`)
)  ENGINE=MyISAM  ;
CREATE TABLE `type_dossier` (
	`id_t` int(11) NOT NULL AUTO_INCREMENT,
	`id_type_dossier` varchar(64) NOT NULL,
	`definition` longtext NOT NULL,
	PRIMARY KEY (`id_t`)
)  ENGINE=MyISAM  ;
CREATE TABLE `type_dossier_action` (
	`id_a` int(11) NOT NULL AUTO_INCREMENT,
	`id_u` int(11) NOT NULL,
	`id_t` int(11) NOT NULL,
	`action` varchar(64) NOT NULL,
	`date` datetime NOT NULL,
	`empreinte_sha256` varchar(128) NOT NULL,
	`message` mediumtext NOT NULL,
	`export_json` longtext NOT NULL,
	PRIMARY KEY (`id_a`),
	KEY `id_t` (`id_t`)
)  ENGINE=MyISAM  ;
CREATE TABLE `users_token` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`id_u` int(11) NOT NULL,
	`name` varchar(64) NOT NULL,
	`token` varchar(250) NOT NULL,
	`created_at` datetime NOT NULL,
	`expired_at` datetime,
	PRIMARY KEY (`id`),
	UNIQUE KEY `users_token_token_uindex` (`token`) 
)  ENGINE=MyISAM  ;
CREATE TABLE `utilisateur` (
	`id_u` int(11) NOT NULL AUTO_INCREMENT,
	`email` varchar(128) NOT NULL,
	`login` varchar(128) NOT NULL,
	`password` varchar(128) NOT NULL,
	`mail_verif_password` varchar(64) NOT NULL,
	`date_inscription` datetime NOT NULL,
	`mail_verifie` tinyint(1) NOT NULL,
	`nom` varchar(128) NOT NULL,
	`prenom` varchar(128) NOT NULL,
	`id_e` int(11) NOT NULL,
	`password_reset_requested_at` datetime NOT NULL,
	`is_enabled` tinyint(1) NOT NULL DEFAULT '1',
	`is_api` int(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`id_u`),
	KEY `id_e` (`id_e`)
)  ENGINE=MyISAM  ;
CREATE TABLE `utilisateur_mfa` (
	`id_u` int(11) NOT NULL,
	`secret` varchar(255) NOT NULL,
	`is_enabled` tinyint(1) NOT NULL DEFAULT '0',
	`created_at` datetime NOT NULL,
	PRIMARY KEY (`id_u`)
)  ENGINE=MyISAM  ;
CREATE TABLE `utilisateur_mfa_recovery_code` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`id_u` int(11) NOT NULL,
	`code_hash` varchar(64) NOT NULL,
	`used_at` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `id_u` (`id_u`)
)  ENGINE=MyISAM  ;
CREATE TABLE `utilisateur_new_email` (
	`id_u` int(11) NOT NULL,
	`email` varchar(255) NOT NULL,
	`password` varchar(32) NOT NULL,
	`date` datetime NOT NULL,
	PRIMARY KEY (`id_u`)
)  ENGINE=MyISAM  ;
CREATE TABLE `utilisateur_role` (
	`id_u` int(11) NOT NULL,
	`role` varchar(64) NOT NULL,
	`id_e` int(11) NOT NULL,
	KEY `id_u` (`id_u`,`id_e`),
	KEY `id_u_2` (`id_e`,`id_u`)
)  ENGINE=MyISAM  ;
CREATE TABLE `worker` (
	`id_worker` int(11) NOT NULL AUTO_INCREMENT,
	`pid` int(11) NOT NULL,
	`date_begin` datetime NOT NULL,
	`id_job` int(11) NOT NULL,
	`date_end` datetime NOT NULL,
	`message` varchar(256) NOT NULL,
	`termine` tinyint(1) NOT NULL,
	`success` tinyint(1) NOT NULL,
	PRIMARY KEY (`id_worker`)
)  ENGINE=MyISAM  ;