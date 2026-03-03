<?php

use Pastell\Service\Document\DocumentDeletionService;

class Purge extends Connecteur
{
    public const GO_TROUGH_STATE = 'GO_TROUGH_STATE';
    public const IN_STATE = 'IN_STATE';

    private DonneesFormulaire $connecteurConfig;

    private string $lastMessage;

    public function __construct(
        private readonly DocumentActionEntite $documentActionEntite,
        private readonly Journal $journal,
        private readonly JobManager $jobManager,
        private readonly ActionPossible $actionPossible,
        private readonly DocumentTypeFactory $documentTypeFactory,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
        private readonly DocumentEntite $documentEntite,
        private readonly DocumentDeletionService $documentDeletionService,
    ) {
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }

    public function isActif()
    {
        return (bool) $this->connecteurConfig->get('actif');
    }

    public function setConnecteurConfig(DonneesFormulaire $donneesFormulaire)
    {
        $this->connecteurConfig = $donneesFormulaire;
    }

    public function listDocument(): array
    {
        $connecteur_info  = $this->getConnecteurInfo();
        $etat_source = $this->connecteurConfig->get('document_etat');
        $type = $this->connecteurConfig->get('document_type');
        $nb_days = (int)$this->connecteurConfig->get('nb_days');
        $passer_par_letat = $this->connecteurConfig->get('passer_par_l_etat');
        $exclure_etat = $this->connecteurConfig->get('document_exclure_etat');
        $selection = [];
        if ($etat_source) {
            if ($passer_par_letat === self::GO_TROUGH_STATE) {
                $selection = $this->documentActionEntite->getDocumentInStateOlderThanDay(
                    $connecteur_info['id_e'],
                    $type,
                    $etat_source,
                    $nb_days
                );
            } else {
                $selection = $this->documentActionEntite->getDocumentOlderThanDay(
                    $connecteur_info['id_e'],
                    $type,
                    $etat_source,
                    $nb_days
                );
            }
        }
        if ($exclure_etat !== '') {
            $selection_exclure = $this->documentActionEntite->getDocumentInStateOlderThanDay(
                $connecteur_info['id_e'],
                $type,
                $exclure_etat,
                $nb_days
            );
            foreach ($selection_exclure as $select_exclure) {
                foreach ($selection as $i => $select) {
                    if ($select_exclure['id_d'] === $select['id_d']) {
                        unset($selection[$i]);
                        break;
                    }
                }
            }
        }
        return $selection;
    }

    public function listDocumentGlobal()
    {
        return $this->documentEntite->getDocumentLastActionOlderThanInDays(
            $this->connecteurConfig->get('nb_days') ?: 5000,
            $this->connecteurConfig->get('document_type')
        );
    }

    public function purgerGlobal()
    {
        if (! $this->isActif()) {
            throw new UnrecoverableException("Le connecteur n'est pas actif");
        }
        foreach ($this->listDocumentGlobal() as $document_info) {
            $this->documentDeletionService->delete($document_info['id_d'], 'Connecteur de purge global');
        }
        $this->lastMessage = 'Les documents ont été purgés';
        return true;
    }

    /**
     * @return bool
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function purger()
    {
        if (! $this->isActif()) {
            throw new UnrecoverableException("Le connecteur n'est pas actif");
        }
        $document_list = $this->listDocument();

        $connecteur_info  = $this->getConnecteurInfo();

        $etat_cible = $this->connecteurConfig->get('document_etat_cible') ?: 'supression';


        $this->lastMessage = 'Programmation de la purge des dossiers     : ';
        foreach ($document_list as $document_info) {
            if ($this->connecteurConfig->get('modification')) {
                $this->modifDocument($document_info['id_e'], $document_info['id_d']);
            }

            if (
                ! $this->actionPossible->isActionPossible(
                    $document_info['id_e'],
                    0,
                    $document_info['id_d'],
                    $etat_cible
                )
            ) {
                $this->lastMessage .= sprintf(
                    '%s : action impossible : %s <br/>',
                    get_hecho("{$document_info['id_d']} - {$document_info['titre']} - {$document_info['last_action_date']}"),
                    $this->actionPossible->getLastBadRule(),
                );
                continue;
            }

            $this->journal->add(
                Journal::DOCUMENT_TRAITEMENT_LOT,
                $document_info['id_e'],
                $document_info['id_d'],
                $etat_cible,
                "Programmation dans le cadre du connecteur de purge {$connecteur_info['id_ce']}"
            );

            $this->jobManager->setTraitementLot(
                $document_info['id_e'],
                $document_info['id_d'],
                0,
                $etat_cible,
                $this->connecteurConfig->get('verrou')
            );
            $this->lastMessage .= sprintf(
                '%s<br/>',
                get_hecho("{$document_info['id_d']} - {$document_info['titre']} - {$document_info['last_action_date']}"),
            );
        }

        return true;
    }

    /**
     * @param $id_e
     * @param $id_d
     * @throws Exception
     */
    private function modifDocument($id_e, $id_d)
    {
        $last_action = $this->documentActionEntite->getLastActionNotModif($id_e, $id_d);
        $documentType = $this->documentTypeFactory->getFluxDocumentType($this->connecteurConfig->get('document_type'));

        $editable_content = $documentType->getAction()->getEditableContent($last_action);
        if (! $editable_content && ! in_array($last_action, ['modification','creation'])) {
            return ;
        }
        if (! is_array($editable_content)) {
            $editable_content = [];
        }

        $donneesFormulaire = $this->donneesFormulaireFactory->get($id_d);

        $modification_definition = $this->connecteurConfig->get('modification');
        $modification_list = explode("\n", $modification_definition);
        foreach ($modification_list as $modifiction_item) {
            $modification_explode = explode(':', $modifiction_item, 2);
            $modification_key = trim($modification_explode[0]);
            $modification_value = trim($modification_explode[1] ?? '');
            if (! $modification_key) {
                continue;
            }

            if (
                ! in_array($modification_key, $editable_content) &&
                ! in_array($last_action, ['modification','creation'])
            ) {
                continue;
            }
            $donneesFormulaire->setData($modification_key, $modification_value);
        }
    }
}
