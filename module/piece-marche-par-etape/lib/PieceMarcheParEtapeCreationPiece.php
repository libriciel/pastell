<?php

class PieceMarcheParEtapeCreationPiece
{
    protected $objectInstancier;

    public function __construct(ObjectInstancier $objectInstancier)
    {
        $this->objectInstancier = $objectInstancier;
    }


    /**
     * @param $pieceMarcheParEtapeData
     * @param $types_pj
     * @param $file_name
     * @param $file_path
     * @return string
     * @throws Exception
     */
    public function creerPieceMarche(PieceMarcheParEtapeData $pieceMarcheParEtapeData, $types_pj, $file_name, $file_path)
    {

        $nom_flux_piece = $pieceMarcheParEtapeData->nom_flux_piece;
        $id_e = $pieceMarcheParEtapeData->id_e;
        $id_u = $pieceMarcheParEtapeData->id_u;
        $envoyer = $pieceMarcheParEtapeData->envoyer;

        if (!$this->objectInstancier->getInstance(DocumentTypeFactory::class)->isTypePresent($nom_flux_piece)) {
            throw new Exception("Le type $nom_flux_piece n'existe pas sur cette plateforme Pastell");
        }
        $new_id_d = $this->objectInstancier->getInstance(DocumentSQL::class)->getNewId();
        $this->objectInstancier->getInstance(DocumentSQL::class)->save($new_id_d, $nom_flux_piece);
        $this->objectInstancier->getInstance(DocumentEntite::class)->addRole($new_id_d, $id_e, "editeur");

        /** @var DonneesFormulaire $donneesFormulaire */
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($new_id_d);

        // Alimentation des attributs
        $donneesFormulaire->setData('libelle', $pieceMarcheParEtapeData->libelle);
        $donneesFormulaire->setData('numero_marche', $pieceMarcheParEtapeData->numero_marche);
        $donneesFormulaire->setData('type_marche', $pieceMarcheParEtapeData->type_marche);
        $donneesFormulaire->setData('recurrent', $pieceMarcheParEtapeData->recurrent);
        $donneesFormulaire->setData('numero_consultation', $pieceMarcheParEtapeData->numero_consultation);
        $donneesFormulaire->setData('type_consultation', $pieceMarcheParEtapeData->type_consultation);
        $donneesFormulaire->setData('etape', $pieceMarcheParEtapeData->etape);
        $donneesFormulaire->setData('soumissionnaire', $pieceMarcheParEtapeData->soumissionnaire);
        $donneesFormulaire->setData('date_document', $pieceMarcheParEtapeData->date_document);
        $donneesFormulaire->setData('montant', $pieceMarcheParEtapeData->montant);

        $donneesFormulaire->setData('type_piece_marche', $types_pj);
        $donneesFormulaire->addFileFromCopy('document', $file_name, $file_path);

        // Affectation du titre au document
        $titre = $donneesFormulaire->getTitre();
        $this->objectInstancier->getInstance(DocumentSQL::class)->setTitre($new_id_d, $titre);

        $actionCreator = new ActionCreatorSQL($this->objectInstancier->getInstance(SQLQuery::class), $this->objectInstancier->getInstance(Journal::class));

        $erreur = false;
        if (!$donneesFormulaire->isValidable()) {
            $erreur = $donneesFormulaire->getLastError();
        }

        if ($erreur) { // création avec erreur
            $message = "Création de la pièce de marché avec erreur: #ID $new_id_d - type : $nom_flux_piece - $titre - $pieceMarcheParEtapeData->etape - $types_pj - Erreur: $erreur";
            $actionCreator->addAction($id_e, $id_u, Action::CREATION, $message, $new_id_d);
            return $message;
        } else { // création succcès
            $message = "Création de la pièce de marché succès #ID $new_id_d - type : $nom_flux_piece - $titre - $pieceMarcheParEtapeData->etape - $types_pj";
            $actionCreator->addAction($id_e, $id_u, Action::MODIFICATION, $message, $new_id_d);


            if ($envoyer) {
                // Valorisation de l'état suivant avec envoyer
                $actionCreator->addAction($id_e, $id_u, 'importation', "Traitement du dossier", $new_id_d);
                $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument($id_e, 0, $new_id_d, 'affectation-orientation');
            } else {
                // Valorisation de l'état suivant sans envoyer
                $actionCreator->addAction($id_e, $id_u, 'importation-sans-envoi', "Traitement du dossier", $new_id_d);
                $this->objectInstancier->getInstance(ActionExecutorFactory::class)->executeOnDocument($id_e, 0, $new_id_d, 'affectation');
            }

            return $message;
        }
    }
}
