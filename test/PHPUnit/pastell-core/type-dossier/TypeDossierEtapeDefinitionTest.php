<?php

use Pastell\Service\TypeDossier\TypeDossierImportService;
use Pastell\Service\TypeDossier\TypeDossierManager;

class TypeDossierEtapeDefinitionTest extends PastellTestCase
{
    public function testWhenHasEtapeWithSameType()
    {
        $typeDossierEtapeDefinition = $this->getObjectInstancier()
            ->getInstance(TypeDossierEtapeManager::class);

        $typeDossierEtape = new TypeDossierEtapeProperties();
        $typeDossierEtape->type = 'depot';
        $typeDossierEtape->num_etape_same_type = 1;
        $typeDossierEtape->etape_with_same_type_exists = true;

        $action_list = $typeDossierEtapeDefinition->getActionForEtape($typeDossierEtape);

        $this->assertEquals([
            'preparation-send-ged_2' =>
                [
                    'name' => 'Préparation de l\'envoi à la GED #2',
                    'rule' =>
                        [
                            'role_id_e' => 'no-role',
                        ],
                    'action-automatique' => 'send-ged_2',
                ],
            'send-ged_2' =>
                [
                    'name-action' => 'Verser à la GED #2',
                    'name' => 'Versé à la GED #2',
                    'rule' =>
                        [
                            'last-action' =>
                                [
                                    0 => 'preparation-send-ged_2',
                                    1 => 'error-ged_2'
                                ],
                        ],
                    'action-automatique' => 'orientation',
                    'action-class' => 'StandardAction',
                    'connecteur-type' => 'GED',
                    'connecteur-type-action' => 'GEDEnvoyer',
                    'connecteur-type-mapping' =>
                        [
                            'fatal-error' => 'error-ged_2',
                        ],
                ],
            'error-ged_2' =>
                [
                    'name' => 'Erreur irrécupérable lors du dépôt #2',
                    'rule' =>
                        [
                            'role_id_e' => 'no-role',
                        ],
                ],
        ], $action_list);
    }

    public function testWhenHasNoEtapeWithSameType()
    {
        $typeDossierEtapeDefinition = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);

        $typeDossierEtape = new TypeDossierEtapeProperties();
        $typeDossierEtape->type = 'depot';

        $action_list = $typeDossierEtapeDefinition->getActionForEtape($typeDossierEtape);
        $this->assertEquals([
            'preparation-send-ged' =>
                [
                    'name' => 'Préparation de l\'envoi à la GED',
                    'rule' =>
                        [
                            'role_id_e' => 'no-role',
                        ],
                    'action-automatique' => 'send-ged',
                ],
            'send-ged' =>
                [
                    'name-action' => 'Verser à la GED',
                    'name' => 'Versé à la GED',
                    'rule' =>
                        [
                            'last-action' =>
                                [
                                    0 => 'preparation-send-ged',
                                    1 => 'error-ged'
                                ],
                        ],
                    'action-automatique' => 'orientation',
                    'action-class' => 'StandardAction',
                    'connecteur-type' => 'GED',
                    'connecteur-type-action' => 'GEDEnvoyer',
                    'connecteur-type-mapping' =>
                        [
                            'fatal-error' => 'error-ged',
                        ],
                ],
            'error-ged' =>
                [
                    'name' => 'Erreur irrécupérable lors du dépôt',
                    'rule' =>
                        [
                            'role_id_e' => 'no-role',
                        ],
                ],
        ], $action_list);
    }


    /**
     * @throws UnrecoverableException
     * @throws TypeDossierException
     */
    public function testMappingWhenHasSameEtape()
    {

        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);
        $typeDossierDefintion = $this->getObjectInstancier()->getInstance(TypeDossierManager::class);
        $typeDossierEtapeDefinition = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);

        $id_t = $typeDossierImportService->importFromFilePath(__DIR__ . "/fixtures/double-parapheur.json")['id_t'];
        $typeDossierData = $typeDossierDefintion->getTypeDossierProperties($id_t);
        $typeDossierEtape = $typeDossierData->etape[1];

        $mapping = $typeDossierEtapeDefinition->getMapping($typeDossierEtape)->getAll();

        $this->assertEquals([
            'iparapheur' => 'iparapheur #2',
            'iparapheur_type' => 'iparapheur_type_2',
            'iparapheur_sous_type' => 'iparapheur_sous_type_2',
            'json_metadata' => 'json_metadata_2',
            'has_date_limite' => 'has_date_limite_2',
            'date_limite' => 'date_limite_2',
            'Signature' => 'Signature #2',
            'iparapheur_dossier_id' => 'iparapheur_dossier_id_2',
            'iparapheur_historique' => 'iparapheur_historique_2',
            'parapheur_last_message' => 'parapheur_last_message_2',
            'has_signature' => 'has_signature_2',
            'signature' => 'signature_2',
            'bordereau_signature' => 'bordereau_signature_2',
            'document_original' => 'document_original_2',
            'multi_document_original' => 'multi_document_original_2',
            'iparapheur_annexe_sortie' => 'iparapheur_annexe_sortie_2',
            'preparation-send-iparapheur' => 'preparation-send-iparapheur_2',
            'send-iparapheur' => 'send-iparapheur_2',
            'verif-iparapheur' => 'verif-iparapheur_2',
            'erreur-verif-iparapheur' => 'erreur-verif-iparapheur_2',
            'recu-iparapheur' => 'recu-iparapheur_2',
            'rejet-iparapheur' => 'rejet-iparapheur_2',
            'iparapheur-sous-type' => 'iparapheur-sous-type_2',
            'envoi_signature' => 'envoi_signature_2',
            'envoi_iparapheur' => 'envoi_iparapheur_2',
            'Parapheur FAST' => 'Parapheur FAST #2',
            'envoi_fast' => 'envoi_fast_2',
            'fast_parapheur_circuit' => 'fast_parapheur_circuit_2',
            'fast_parapheur_circuit_configuration' => 'fast_parapheur_circuit_configuration_2',
            'send-signature-error' => 'send-signature-error_2',
            'fast_parapheur_email_destinataire' => 'fast_parapheur_email_destinataire_2',
            'fast_parapheur_email_cc' => 'fast_parapheur_email_cc_2',
            'fast_parapheur_agents' => 'fast_parapheur_agents_2',
            'annotation_publique' => 'annotation_publique_2',
            'annotation_privee' => 'annotation_privee_2',
            'primo_signature_detachee' => 'primo_signature_detachee_2',
            'parapheur_date_signature' => 'parapheur_date_signature_2',
            'iparapheur_metadata_sortie' => 'iparapheur_metadata_sortie_2',
        ], $mapping);
    }

    /**
     * @throws UnrecoverableException
     * @throws TypeDossierException
     */
    public function testMappingWhenHasNotSameEtape()
    {

        $typeDossierImportService = $this->getObjectInstancier()->getInstance(TypeDossierImportService::class);
        $id_t = $typeDossierImportService->importFromFilePath(__DIR__ . "/fixtures/ged-only.json")['id_t'];

        $typeDossierService = $this->getObjectInstancier()->getInstance(TypeDossierManager::class);
        $typeDossierData = $typeDossierService->getTypeDossierProperties($id_t);
        $typeDossierEtape = $typeDossierData->etape[0];
        $typeDossierEtapeDefinition = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);
        $mapping = $typeDossierEtapeDefinition->getMapping($typeDossierEtape)->getAll();
        $this->assertEmpty($mapping);
    }


    public function testGetFormulaireWhenHasSameEtape()
    {
        $typeDossierEtapeDefinition = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);

        $typeDossierEtape = new TypeDossierEtapeProperties();
        $typeDossierEtape->type = 'signature';
        $typeDossierEtape->num_etape_same_type = 1;
        $typeDossierEtape->etape_with_same_type_exists = true;

        $action_list = $typeDossierEtapeDefinition->getFormulaireForEtape($typeDossierEtape);

        $this->assertEquals([
            'iparapheur #2' =>
                [
                    'iparapheur_type_2' =>
                        [
                            'name' => 'Type iparapheur',
                            'read-only' => true,
                        ],
                    'iparapheur_sous_type_2' =>
                        [
                            'name' => 'Sous-type iparapheur',
                            'requis' => true,
                            'index' => true,
                            'type' => 'externalData',
                            'choice-action' => 'iparapheur-sous-type_2',
                            'link_name' => 'Sélectionner un sous-type',
                        ],
                    'json_metadata_2' =>
                        [
                            'name' => 'Métadonnées parapheur (JSON)',
                            'commentaire' => 'Au format JSON {"cle1":"valeur1","cle2":"valeur2",...}',
                            'type' => 'file',
                            'visionneuse' => 'Pastell\Viewer\MetadonneesViewer',
                            'visionneuse-no-link' => true,
                        ],
                    'has_date_limite_2' =>
                        [
                            'name' => 'Utiliser une date limite',
                            'type' => 'checkbox',
                        ],
                    'date_limite_2' =>
                        [
                            'name' => 'Date limite',
                            'type' => 'date',
                        ],
                    'envoi_iparapheur_2' => [
                        'no-show' => true
                    ],
                    'annotation_publique_2' => [
                        'name' => 'Annotation publique',
                        'type' => 'textarea',
                    ],
                    'annotation_privee_2' => [
                        'name' => 'Annotation privée',
                        'type' => 'textarea',
                    ],
                    'primo_signature_detachee_2' => [
                        'name' => 'Primo-signature détachée',
                        'type' => 'file',
                        'multiple' => true,
                        'commentaire' => 'format XML ou pkcs7',
                    ],
                ],
            'Signature #2' =>
                [
                    'iparapheur_dossier_id_2' =>
                        [
                            'name' => '#ID dossier parapheur',
                            'read-only' => true,
                        ],
                    'iparapheur_historique_2' =>
                        [
                            'name' => 'Historique iparapheur',
                            'type' => 'file',
                            'read-only' => true,
                        ],
                    'parapheur_last_message_2' => [
                        'name' => 'Dernier message reçu du parapheur',
                        'read-only' => true,
                    ],
                    'has_signature_2' =>
                        [
                            'no-show' => true,
                            'read-only' => true,
                        ],
                    'signature_2' =>
                        [
                            'name' => 'Signature détachée',
                            'type' => 'file',
                            'read-only' => true,
                        ],
                    'bordereau_signature_2' => [
                        'name' => 'Bordereau de signature',
                        'type' => 'file',
                        'read-only' => true,
                    ],
                    'document_original_2' =>
                        [
                            'name' => 'Document original',
                            'type' => 'file',
                            'read-only' => true,
                        ],
                    'multi_document_original_2' =>
                        [
                            'name' => 'Multi-document(s) original',
                            'type' => 'file',
                            'multiple' => true,
                            'read-only' => true,
                        ],
                    'iparapheur_annexe_sortie_2' =>
                        [
                            'name' => 'Annexe(s) de sortie du parapheur',
                            'type' => 'file',
                            'multiple' => true,
                            'read-only' => true,
                        ],
                    'parapheur_date_signature_2' =>
                        [
                            'name' => 'Date de dernière signature',
                            'type' => 'date',
                            'read-only' => true,
                        ],
                    'iparapheur_metadata_sortie_2' =>
                        [
                            'name' => 'Métadonnées de sortie du parapheur',
                            'type' => 'file',
                            'read-only' => true,
                            'visionneuse' => 'Pastell\Viewer\MetadonneesViewer',
                            'visionneuse-no-link' => true,
                        ],
                ],
            'Parapheur FAST #2' => [
                'envoi_fast_2' => [
                    'no-show' => true
                ],
                'fast_parapheur_circuit_2' => [
                    'name' => 'Circuit sur le parapheur',
                    'type' => 'externalData',
                    'choice-action' => 'iparapheur-sous-type_2',
                    'link_name' => 'Liste des circuits'
                ],
                'fast_parapheur_circuit_configuration_2' => [
                    'name' => 'Configuration du circuit à la volée (au format JSON)',
                    'commentaire' => 'Si ce fichier est déposé, il remplace le circuit choisi dans le champ "Circuit sur le parapheur"',
                    'type' => 'file'
                ],
                'fast_parapheur_email_destinataire_2' => [
                    'name' => 'Email du destinataire',
                    'commentaire' => 'Email de la personne à qui l’on souhaite envoyer le document après sa signature<br />
Uniquement avec le mode "circuit à la volée"',
                ],
                'fast_parapheur_email_cc_2' => [
                    'name' => 'Email des destinataires en copie carbone',
                    'commentaire' => 'Permet de rajouter des destinataires mais en copie carbone<br />
Uniquement avec le mode "circuit à la volée"',
                ],
                'fast_parapheur_agents_2' => [
                    'name' => 'Emails des agents',
                    'commentaire' => 'Les emails des utilisateurs à rajouter en tant qu’agent. Séparé par des virgules<br />
Uniquement avec le mode "circuit à la volée"',
                ],
            ]
        ], $action_list);
    }


    public function testGetActionWhenHasSameEtape()
    {
        $typeDossierEtapeDefinition = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);

        $typeDossierEtape = new TypeDossierEtapeProperties();
        $typeDossierEtape->type = 'signature';
        $typeDossierEtape->num_etape_same_type = 1;
        $typeDossierEtape->etape_with_same_type_exists = true;

        $action_list = $typeDossierEtapeDefinition->getActionForEtape($typeDossierEtape);

        $this->assertEquals(
            [
                'preparation-send-iparapheur_2' => [
                    'name' => 'Préparation de l\'envoi au parapheur #2',
                    'rule' => [
                        'role_id_e' => 'no-role',
                    ],
                    'action-automatique' => 'send-iparapheur_2',
                ],
                'send-iparapheur_2' => [
                    'name-action' => 'Transmettre au parapheur #2',
                    'name' => 'Transmis au parapheur #2',
                    'rule' => [
                        'last-action' => [
                            'preparation-send-iparapheur_2',
                            'send-signature-error_2'
                        ],
                    ],
                    'action-class' => 'StandardAction',
                    'connecteur-type' => 'signature',
                    'connecteur-type-action' => 'SignatureEnvoie',
                    'connecteur-type-mapping' => [
                        'iparapheur_type' => 'iparapheur_type_2',
                        'iparapheur_sous_type' => 'iparapheur_sous_type_2',
                        'iparapheur_dossier_id' => 'iparapheur_dossier_id_2',
                        'json_metadata' => 'json_metadata_2',
                        'fast_parapheur_circuit' => 'fast_parapheur_circuit_2',
                        'send-signature-error' => 'send-signature-error_2',
                        'fast_parapheur_circuit_configuration' => 'fast_parapheur_circuit_configuration_2',
                        'fast_parapheur_email_destinataire' => 'fast_parapheur_email_destinataire_2',
                        'fast_parapheur_email_cc' => 'fast_parapheur_email_cc_2',
                        'fast_parapheur_agents' => 'fast_parapheur_agents_2',
                        'iparapheur_annotation_publique' => 'annotation_publique_2',
                        'iparapheur_annotation_privee' => 'annotation_privee_2',
                        'primo_signature_detachee' => 'primo_signature_detachee_2',
                    ],
                    'action-automatique' => 'verif-iparapheur_2',
                ],
                'verif-iparapheur_2' => [
                    'name-action' => 'Vérifier le statut de signature #2',
                    'name' => 'Vérification de la signature #2',
                    'rule' => [
                        'last-action' => [
                            'erreur-verif-iparapheur_2',
                            'send-iparapheur_2',
                        ],
                    ],
                    'action-class' => 'StandardAction',
                    'connecteur-type' => 'signature',
                    'connecteur-type-action' => 'SignatureRecuperation',
                    'connecteur-type-mapping' => [
                        'iparapheur_historique' => 'iparapheur_historique_2',
                        'has_signature' => 'has_signature_2',
                        'signature' => 'signature_2',
                        'document_original' => 'document_original_2',
                        'multi_document_original' => 'multi_document_original_2',
                        'bordereau' => 'bordereau_signature_2',
                        'iparapheur_annexe_sortie' => 'iparapheur_annexe_sortie_2',
                        'iparapheur_dossier_id' => 'iparapheur_dossier_id_2',
                        'recu-iparapheur' => 'recu-iparapheur_2',
                        'rejet-iparapheur' => 'rejet-iparapheur_2',
                        'erreur-verif-iparapheur' => 'erreur-verif-iparapheur_2',
                        'parapheur_last_message' => 'parapheur_last_message_2',
                        'parapheur_date_signature' => 'parapheur_date_signature_2',
                        'iparapheur_metadata_sortie' => 'iparapheur_metadata_sortie_2',
                    ],
                ],
                'erreur-verif-iparapheur_2' => [
                    'name' => 'Erreur lors de la vérification du statut de signature #2',
                    'rule' => [
                        'role_id_e' => 'no-role',
                    ],
                ],
                'recu-iparapheur_2' => [
                    'name' => 'Signature récupérée #2',
                    'rule' => [
                        'role_id_e' => 'no-role',
                    ],
                    'action-automatique' => 'orientation',
                ],
                'rejet-iparapheur_2' => [
                    'name' => 'Signature refusée #2',
                    'rule' => [
                        'role_id_e' => 'no-role',
                    ],
                ],
                'iparapheur-sous-type_2' => [
                    'name' => 'Liste des sous-type iparapheur #2',
                    'no-workflow' => true,
                    'rule' => [
                        'role_id_e' => 'no-role',
                    ],
                    'action-class' => 'IparapheurSousType',
                    'connecteur-type-mapping' => [
                        'iparapheur_type' => 'iparapheur_type_2',
                        'iparapheur_sous_type' => 'iparapheur_sous_type_2',
                        'fast_parapheur_circuit' => 'fast_parapheur_circuit_2'
                    ],
                ],
                'send-signature-error_2' => [
                    'name' => "Erreur lors de l'envoi du dossier à la signature #2",
                    'editable-content' => [
                        'iparapheur_sous_type_2',
                        'json_metadata_2',
                        'has_date_limite_2',
                        'date_limite_2',
                        'fast_parapheur_circuit_2',
                        'fast_parapheur_circuit_configuration_2',
                        'fast_parapheur_email_destinataire_2',
                        'fast_parapheur_email_cc_2',
                        'fast_parapheur_agents_2',
                    ],
                    'rule' => [
                        'role_id_e' => 'no-role',
                    ],
                    'modification-no-change-etat' => true
                ]
            ],
            $action_list
        );
    }

    public function testGetPageCondition()
    {
        $typeDossierEtapeDefinition = $this->getObjectInstancier()->getInstance(TypeDossierEtapeManager::class);

        $typeDossierEtape = new TypeDossierEtapeProperties();
        $typeDossierEtape->type = 'signature';
        $typeDossierEtape->num_etape_same_type = 1;
        $typeDossierEtape->etape_with_same_type_exists = true;

        $page_condition = $typeDossierEtapeDefinition->getPageCondition($typeDossierEtape);

        $this->assertEquals([
            'iparapheur #2' => [
                'envoi_iparapheur_2' => true
            ],
            'Signature #2' =>
                [
                    'has_signature_2' => true,
                ],
            'Parapheur FAST #2' => [
                'envoi_fast_2' => true
            ]
        ], $page_condition);
    }
}
