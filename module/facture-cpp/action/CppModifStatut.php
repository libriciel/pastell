<?php

class CppModifStatut extends ActionExecutor
{
    private $statut_consomme_liste = '';

    // Retourne :
    // statut : le statut final du document
    // statut_consomme_array : les statuts consommés (déjà traités) par l'action.
    /**
     * @return array|bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    public function metier()
    {
        $doc = $this->getDonneesFormulaire();
        $statut_cible_liste = $doc->get(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE);
        $statut_cpp = $doc->get(AttrFactureCPP::ATTR_STATUT_CPP);
        $motif_maj = $doc->get(AttrFactureCPP::ATTR_MOTIF_MAJ);
        // Adaptation de la liste des statuts : consommation de la liste des statuts jusqu'au statut cible compris.
        $statut_cible_liste = $this->consommerStatutCibleListe($statut_cible_liste, $statut_cpp);
        if (!$statut_cible_liste) {
            // Formatage de la liste de statuts consommés en tableau
            $statut_consomme_array = $this->statut_consomme_liste;
            if ($statut_consomme_array) {
                $statut_consomme_array = substr($statut_consomme_array, 0, -1);
                $statut_consomme_array = explode(';', $statut_consomme_array);
            }
            return ["statut" => $statut_cpp, "statut_consomme_array" => $statut_consomme_array];
        }
        // Controle de la validité de la liste des statuts cibles.
        if (!$this->controlerListeStatutCible($statut_cible_liste, $motif_maj)) {
            return false;
        }
        // Récupération du 1er statut cible de la liste.
        $statut_cible = $this->getStatutCible($statut_cible_liste);
        // Si aucun statut cible n'existe après la consommation, les statuts ont déjà été consommés. On retourne le statut courant
        // Sinon on effectue le changement de statut.
        if ($statut_cible) {
            if (!$this->modifStatut($statut_cible)) {
                return false;
            }
            $this->consommerStatutCibleListe($statut_cible_liste, $statut_cible, false);
            $statut_done = $statut_cible;
        } else {
            $statut_done = $statut_cpp;
        }
        // Formatage de la liste de statuts consommés en tableau
        $statut_consomme_array = $this->statut_consomme_liste;
        if ($statut_consomme_array) {
            $statut_consomme_array = substr($statut_consomme_array, 0, -1);
            $statut_consomme_array = explode(';', $statut_consomme_array);
        }
        return ["statut" => $statut_done, "statut_consomme_array" => $statut_consomme_array];
    }

    /**
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        $doc = $this->getDonneesFormulaire();

        if (!$doc->get('is_cpp')) {
            $statut_cible_tab = explode(";", $doc->get(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE));
            $statut_cible = end($statut_cible_tab);
            $doc->setData(AttrFactureCPP::ATTR_STATUT_CPP, $statut_cible);
            $doc->setData(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE, "");
            $doc->setData(AttrFactureCPP::ATTR_DATE_PASSAGE_STATUT, date("Y-m-d"));

            $histo_content = $doc->getFileContent('histo_statut_cpp');

            $utilisateurSQL = $this->objectInstancier->getInstance(UtilisateurSQL::class);
            $utilisateur_info = $utilisateurSQL->getInfo($this->id_u);

            $commentaire = $doc->get(AttrFactureCPP::ATTR_MOTIF_MAJ);

            $histoStatutCPP = new HistoStatutCPP();
            $histo_content = $histoStatutCPP->addStatut(
                $histo_content,
                $statut_cible,
                $commentaire,
                $utilisateur_info['nom'],
                $utilisateur_info['prenom']
            );

            $doc->addFileFromData('histo_statut_cpp', 'histo_statut_cpp.json', $histo_content, 0);

            if ($statut_cible == PortailFactureConnecteur::STATUT_SUSPENDUE) {
                $doc->setData(AttrFactureCPP::ATTR_ID_FACTURE_CPP, $doc->get('id_facture_cpp') . "-2-SUSPENDUE");
                $doc->setData(AttrFactureCPP::ATTR_IS_ANNULE, true);
            }

            $message = "La facture est en statut " . $statut_cible;
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'cpp-modif-statut-ok', $message);
            $this->notify('cpp-modif-statut-ok', $this->type, $message);
            return true;
        }

        /** @var CPP $connPortailFacture */
        $connPortailFacture = $this->getConnecteur('PortailFacture');

        if ($connPortailFacture->getNoChangeStatutChorus()) {
            $message = "La remontée de la modification de statut sur Chorus Pro est désactivée.";
            $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'cpp-modif-statut-erreur', $message);
            return false;
        }
        try {
            $result_modif = $this->metier();
            if (!$result_modif) {
                $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'cpp-modif-statut-erreur', $this->getLastMessage());
                $this->notify('cpp-modif-statut-erreur', $this->type, $this->getLastMessage());
                return false;
            } else {
                if ($result_modif['statut_consomme_array']) {
                    $message = "La facture est déja en statut " . $result_modif['statut'];
                    $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'cpp-modif-statut-ok', $message);
                    $this->notify('cpp-modif-statut-ok', $this->type, $message);
                    return true;
                }

                $this->addActionOK('La facture est en statut ' . $result_modif['statut']);

                $doc = $this->getDonneesFormulaire();
                $statut_cible_liste = $doc->get(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE);
                $statut_cpp = $doc->get(AttrFactureCPP::ATTR_STATUT_CPP);

                if ($statut_cible_liste) {
                    $message = "Demande de modification en statut cible " . $this->getStatutCible($statut_cible_liste);
                    $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'cpp-modif-statut-demande', $message);
                } else {
                    $message = "La facture est en statut " . $statut_cpp;
                    $this->getActionCreator()->addAction($this->id_e, $this->id_u, 'cpp-modif-statut-ok', $message);
                    $this->notify('cpp-modif-statut-ok', $this->type, $message);

                    /** @var PortailFactureConnecteur $portailFactureConnecteur */
                    $portailFactureConnecteur = $this->getConnecteur('PortailFacture');
                    $synchronisationFacture = new SynchronisationFacture($portailFactureConnecteur);
                    $result_synchro = $synchronisationFacture->getSynchroDocumentFacture($this->getDonneesFormulaire(), true);

                    $this->objectInstancier
                        ->getInstance(Journal::class)
                        ->addSQL(
                            Journal::DOCUMENT_ACTION,
                            $this->id_e,
                            $this->id_u,
                            $this->id_d,
                            'synchroniser-statut',
                            $synchronisationFacture->formatResultSynchro($result_synchro)
                        );
                }
            }
        } catch (Exception $e) {
            $this->setLastMessage('ERREUR : ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param $statut_cible_liste
     * @param $statut_cpp
     * @param bool $historiser_statut_consomme
     * @return string
     * @throws NotFoundException
     */
    private function consommerStatutCibleListe($statut_cible_liste, $statut_cpp, $historiser_statut_consomme = true): string
    {
        $doc = $this->getDonneesFormulaire();

        $statut_cible_tab = explode(";", $statut_cible_liste);
        $statut_cible_new_liste = "";

        foreach ($statut_cible_tab as $statut_cible) {
            if ($statut_cpp == $statut_cible) {
                if ($historiser_statut_consomme) {
                    $this->statut_consomme_liste .= $statut_cible_new_liste . "$statut_cible;";
                }
                $statut_cible_new_liste = "";
            } else {
                $statut_cible_new_liste .= $statut_cible . ";";
            }
        }
        $statut_cible_new_liste = substr($statut_cible_new_liste, 0, -1);
        $doc->setData(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE, $statut_cible_new_liste);

        return $statut_cible_new_liste;
    }


    // Contrat de sortie :
    //  - true si le controle est ok
    //  - exception en cas de non conformité.
    /**
     * @param $statut_cible_liste
     * @param $motif_maj
     * @return bool
     */
    protected function controlerListeStatutCible($statut_cible_liste, $motif_maj)
    {
        // Vérifier que la liste n'est pas vide
        if (empty($statut_cible_liste)) {
            $this->setLastMessage('La liste des statuts cible est vide.');
            return false;
        }

        $statuts_cible_autorisees = [
            PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION,
            PortailFactureConnecteur::STATUT_REJETEE,
            PortailFactureConnecteur::STATUT_SUSPENDUE,
            PortailFactureConnecteur::STATUT_SERVICE_FAIT,
            PortailFactureConnecteur::STATUT_MANDATEE,
            PortailFactureConnecteur::STATUT_MISE_A_DISPOSITION_COMPTABLE,
            PortailFactureConnecteur::STATUT_COMPTABILISEE,
            PortailFactureConnecteur::STATUT_MISE_EN_PAIEMENT
        ];

        $statut_cible_tab = explode(";", $statut_cible_liste);

        // Vérifier :
        //  - la présence du motif en fonction des statuts
        //  - la validité des statuts
        foreach ($statut_cible_tab as $statut_cible) {
            if (
                (($statut_cible == PortailFactureConnecteur::STATUT_REJETEE)
                || ($statut_cible == PortailFactureConnecteur::STATUT_SUSPENDUE))
                && (!$motif_maj)
            ) {
                $message = 'Le statut cible ' . $statut_cible . ' nécessite un motif';
                $this->setLastMessage($message);
                return false;
            }
            if (!in_array($statut_cible, $statuts_cible_autorisees)) {
                $message = "Le statut cible $statut_cible n'existe pas.";
                $this->setLastMessage($message);
                return false;
            }
        }
        return true;
    }

    // retourne le 1er statut de la liste
    // retourne false si la liste est vide (déjà consommée)
    /**
     * @param $statut_cible_liste
     * @return bool|mixed
     */
    private function getStatutCible($statut_cible_liste)
    {
        if (empty($statut_cible_liste)) {
            return false;
        }
        $statut_cible_tab = explode(";", $statut_cible_liste);
        return $statut_cible_tab[0];
    }

    /**
     * @param $statut_cible
     * @return bool
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    private function modifStatut($statut_cible)
    {
        $doc = $this->getDonneesFormulaire();
        $statut_cpp = $doc->get(AttrFactureCPP::ATTR_STATUT_CPP);
        $id_facture_cpp = $doc->get(AttrFactureCPP::ATTR_ID_FACTURE_CPP);
        $motif_maj = substr($doc->get(AttrFactureCPP::ATTR_MOTIF_MAJ, ''), 0, 255);
        $numero_mandat = '';
        if ($statut_cible == PortailFactureConnecteur::STATUT_MANDATEE) {
            $numero_mandat = $doc->get(AttrFactureCPP::ATTR_NUMERO_MANDAT, '');
        }

        if ($statut_cpp === $statut_cible) {
            // Le statut est déjà positionné, rien à faire.
            return true;
        }
        // Traitement de modification du statut sur CPP
        /** @var PortailFactureConnecteur $connPortailFacture */
        $connPortailFacture = $this->getConnecteur('PortailFacture');
        $result = $connPortailFacture->setStatutFacture($id_facture_cpp, $statut_cible, $motif_maj, $numero_mandat);
        if ($result) {
            if (isset($result['retourFonctionnel'])) {
                // Resynchronisation du document avec CPP.
                $synchronisationFacture = new SynchronisationFacture($connPortailFacture);
                $synchronisationFacture->getSynchroDocumentFacture($this->getDonneesFormulaire(), false);
                $statut_cpp = $doc->get(AttrFactureCPP::ATTR_STATUT_CPP);
                $statut_cible_liste = explode(";", $doc->get(AttrFactureCPP::ATTR_STATUT_CIBLE_LISTE));


                // Si le nouveau statut est différent et s'il fait partie de la liste des statuts cible, on retente la modification.
                if (($statut_cpp !== $statut_cible) && in_array($statut_cpp, $statut_cible_liste)) {
                    $statut_cible_liste = $this->consommerStatutCibleListe($statut_cible_liste, $statut_cpp);
                    $statut_cible = $this->getStatutCible($statut_cible_liste);
                    if ($statut_cible) {
                        $result = $connPortailFacture->setStatutFacture($id_facture_cpp, $statut_cible, $motif_maj, $numero_mandat);
                    } else {
                        // L'état a été consommée. Rien à faire.
                        return true;
                    }
                    if (isset($result['retourFonctionnel'])) {
                        $this->setLastMessage($result['retourFonctionnel'] . ' - ' . $result['libelleRetourFonctionnel']);
                        return false;
                    }
                } else {
                    // Sinon on retourne l'erreur
                    $this->setLastMessage($result['retourFonctionnel'] . ' - ' . $result['libelleRetourFonctionnel']);
                    return false;
                }
            }
            $doc->setData(AttrFactureCPP::ATTR_STATUT_CPP, $statut_cible);
        }
        return true;
    }
}
