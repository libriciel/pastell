SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


TRUNCATE TABLE `agent`;
TRUNCATE TABLE `annuaire`;
TRUNCATE TABLE `annuaire_groupe`;
INSERT INTO `annuaire_groupe` (`id_g`, `id_e`, `nom`, `partage`) VALUES
(1, 1, 'Mon groupe', 0),
(2, 1, 'Elu', 0);

TRUNCATE TABLE `annuaire_groupe_contact`;
TRUNCATE TABLE `annuaire_role`;
TRUNCATE TABLE `collectivite_fournisseur`;
TRUNCATE TABLE `connecteur_entite`;
INSERT INTO `connecteur_entite` (`id_ce`, `id_e`, `libelle`, `id_connecteur`, `type`, `frequence_en_minute`, `id_verrou`) VALUES
(1, 1, 'Fake iParapheur', 'fakeIparapheur', 'signature', 1, ''),
(2, 1, 'Fake Tdt', 'fakeTdt', 'TdT', 1, ''),
(3, 1, 'SEDA Standard', 'actes-seda-standard', 'Bordereau SEDA', 1, ''),
(4, 1, 'Fake SAE', 'fakeSAE', 'SAE', 1, ''),
(5, 1, 'Fake GED', 'FakeGED', 'GED', 1, ''),
(6, 1, 'SEDA CG86', 'actes-seda-cg86', 'Bordereau SEDA', 1, ''),
(7, 1, 'SEDA locarchive', 'actes-seda-locarchive', 'Bordereau SEDA', 1, ''),
(8, 1, 'SEDA parametrable', 'actes-seda-parametrable', 'Bordereau SEDA', 1, ''),
(9, 1, 'mail-fournisseur-invitation', 'mail-fournisseur-invitation', 'mail-fournisseur-invitation', 1, ''),
(10, 0, 'Horodateur interne par défaut', 'horodateur-interne', 'horodateur', 1, ''),
(11, 1, 'Mail securise', 'mailsec', 'mailsec', 1, ''),
(12, 1, 'connecteur non associé', 'test', 'test', 1, ''),
(13, 1, 'Connecteur de test', 'test', 'test', 1, 'toto');

TRUNCATE TABLE `connecteur_frequence`;
INSERT INTO `connecteur_frequence` (`id_cf`, `type_connecteur`, `famille_connecteur`, `id_connecteur`, `id_ce`, `action_type`, `type_document`, `action`, `expression`, `id_verrou`) VALUES
(1, '', '', '', 0, '', '', '', '2', 'DEFAULT_FREQUENCE'),
(2, 'entite', '', 'i-parapheur', 42, 'document', 'actes-generique', 'verif-tdt', '30', '');

TRUNCATE TABLE `document`;
TRUNCATE TABLE `document_action`;
TRUNCATE TABLE `document_action_entite`;
TRUNCATE TABLE `document_email`;
TRUNCATE TABLE `document_email_reponse`;
TRUNCATE TABLE `document_entite`;
TRUNCATE TABLE `document_index`;
TRUNCATE TABLE `droit`;
TRUNCATE TABLE `entite`;
INSERT INTO `entite` (`id_e`, `type`, `denomination`, `siren`, `date_inscription`, `entite_mere`, `centre_de_gestion`, `is_active`) VALUES
(1, 'collectivite', 'Bourg-en-Bresse', '000000000', '0000-00-00 00:00:00', '0', 0, 1),
(2, 'collectivite', 'CCAS', '111111118', '0000-00-00 00:00:00', '1', 0, 1);

TRUNCATE TABLE `entite_ancetre`;
INSERT INTO `entite_ancetre` (`id_e_ancetre`, `id_e`, `niveau`) VALUES
(0, 0, 0),
(0, 1, 1),
(1, 1, 0),
(2, 2, 0),
(1, 2, 1),
(0, 2, 2);

TRUNCATE TABLE `entite_properties`;
TRUNCATE TABLE `extension`;
INSERT INTO `extension` (`id_e`, `nom`, `path`) VALUES
(1, 'pastell_cdg59', '/var/lib/pastell/pastell_cdg59'),
(2, 'pastell_stela', '/var/lib/pastell/pastell_stela');

TRUNCATE TABLE `flux_entite`;
INSERT INTO `flux_entite` (`id_fe`, `id_e`, `flux`, `id_ce`, `type`) VALUES
(1, 1, 'actes-generique', 1, 'signature'),
(2, 1, 'actes-generique', 2, 'TdT'),
(3, 1, 'actes-generique', 3, 'Bordereau SEDA'),
(4, 1, 'actes-generique', 4, 'SAE'),
(5, 1, 'actes-generique', 5, 'GED'),
(6, 1, 'fournisseur-invitation', 9, 'mail-fournisseur-invitation'),
(7, 0, 'global', 10, 'horodateur'),
(8, 1, 'mailsec', 11, 'mailsec'),
(9, 1, 'test', 13, 'test');

TRUNCATE TABLE `flux_entite_heritage`;
TRUNCATE TABLE `grade`;
TRUNCATE TABLE `job_queue`;
TRUNCATE TABLE `journal`;
TRUNCATE TABLE `journal_attente_preuve`;
TRUNCATE TABLE `journal_historique`;
TRUNCATE TABLE `notification`;
TRUNCATE TABLE `notification_digest`;
TRUNCATE TABLE `role`;
INSERT INTO `role` (`role`, `libelle`) VALUES
('admin', 'Administrateur'),
('utilisateur', 'utilisateur sans rôle'),
('autre', 'autre rôle');

TRUNCATE TABLE `role_droit`;
INSERT INTO `role_droit` (`role`, `droit`) VALUES
('admin', 'actes-generique:edition'),
('admin', 'actes-generique:lecture'),
('admin', 'helios-generique:edition'),
('admin', 'helios-generique:lecture'),
('admin', 'annuaire:edition'),
('admin', 'annuaire:lecture'),
('admin', 'entite:edition'),
('admin', 'entite:lecture'),
('admin', 'connecteur:edition'),
('admin', 'connecteur:lecture'),
('admin', 'fournisseur-invitation:edition'),
('admin', 'fournisseur-invitation:lecture'),
('admin', 'journal:lecture'),
('admin', 'mailsec:edition'),
('admin', 'mailsec:lecture'),
('admin', 'message-service:edition'),
('admin', 'message-service:lecture'),
('admin', 'role:edition'),
('admin', 'role:lecture'),
('admin', 'system:edition'),
('admin', 'system:lecture'),
('admin', 'test:edition'),
('admin', 'test:lecture'),
('admin', 'utilisateur:creation'),
('admin', 'utilisateur:edition'),
('admin', 'utilisateur:lecture'),
('admin', 'actes-preversement-seda:edition'),
('admin', 'actes-preversement-seda:lecture'),
('admin', 'actes-automatique:edition'),
('admin', 'actes-automatique:lecture'),
('admin', 'helios-automatique:edition'),
('admin', 'helios-automatique:lecture'),
('admin', 'document-a-signer:edition'),
('admin', 'document-a-signer:lecture'),
('admin', 'actes-reponse-prefecture:edition'),
('admin', 'actes-reponse-prefecture:lecture'),
('admin', 'pdf-generique:edition'),
('admin', 'pdf-generique:lecture'),
('admin', 'commande-generique:edition'),
('admin', 'commande-generique:lecture'),
('admin', 'mailsec-bidir:edition'),
('admin', 'mailsec-bidir:lecture'),
('admin', 'ls-document-pdf:edition'),
('admin', 'ls-document-pdf:lecture');


TRUNCATE TABLE `utilisateur`;
INSERT INTO `utilisateur` (`id_u`, `email`, `login`, `password`, `mail_verif_password`, `date_inscription`, `mail_verifie`, `nom`, `prenom`, `certificat`, `certificat_verif_number`, `id_e`) VALUES
(1, 'eric@sigmalis.com', 'admin', '$2y$10$EzBRHHhgaJ.PPbAsMp0OXOl3LstyyGnOi4rD6vj361z7dXAg6kxKG', '', '0000-00-00 00:00:00', 1, 'Pommateau', 'Eric', '', '', 0),
(2, 'eric2@sigmalis.com', 'eric', 'gee4Zoom', '', '0000-00-00 00:00:00', 1, 'Pommateau', 'Eric', '', '', 0);

TRUNCATE TABLE `utilisateur_new_email`;
TRUNCATE TABLE `utilisateur_role`;
INSERT INTO `utilisateur_role` (`id_u`, `role`, `id_e`) VALUES
(1, 'admin', 0),
(2, 'admin', 1);

TRUNCATE TABLE `worker`;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
TRUNCATE TABLE type_dossier;
TRUNCATE TABLE users_token;
