<?php

class ActesPreversementSEDACreate extends ActionExecutor
{
    public const FLUX_NAME = 'actes-automatique';
    public const ACTES_NAMESPACE = "http://www.interieur.gouv.fr/ACTES#v1.1-20040216";

    /**
     * @throws NotFoundException
     * @throws SimpleXMLWrapperException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        $enveloppe_metier = $this->getDonneesFormulaire()->getFilePath('enveloppe_metier');

        $simpleXMLWrapper = new SimpleXMLWrapper();

        $xml = $simpleXMLWrapper->loadFile($enveloppe_metier);

        $attributes = $xml->attributes(self::ACTES_NAMESPACE);

        $code_nature = $this->retrieveAttributes($attributes, 'CodeNatureActe');
        $numero_interne = $this->retrieveAttributes($attributes, 'NumeroInterne');
        $numero_interne = preg_replace("#-#", "_", $numero_interne);
        $numero_interne = strtoupper($numero_interne);
        $date = $this->retrieveAttributes($attributes, 'Date');

        $children = $xml->children(self::ACTES_NAMESPACE);

        $objet = $this->retrieveChildrenContent($children, 'Objet');

        $code_matiere = [];
        for ($i = 1; $i <= 5; $i++) {
            if (isset($children->{"CodeMatiere$i"}['CodeMatiere'])) {
                $code_matiere[] = strval($children->{"CodeMatiere$i"}['CodeMatiere']);
            }
        }
        $code_matiere = implode('.', $code_matiere);

        $documents = $this->getDonneesFormulaire()->get('document');
        /** @var Fichier[] $files */
        $files = [];

        $enveloppeFilename = (string)$children->Document->NomFichier;

        $files[] = $this->getFileFromEnveloppe($enveloppeFilename, $documents);

        $typology = $this->getFileTypology($enveloppeFilename, $code_nature);
        $type_acte = $typology;

        $type_piece_file[] = ['filename' => $enveloppeFilename, 'typologie' => $typology];

        $type_pj = [];
        if (!empty($children->Annexes->Annexe)) {
            foreach ($children->Annexes->Annexe as $annex) {
                $enveloppeFilename = (string)$annex->NomFichier;
                $files[] = $this->getFileFromEnveloppe($enveloppeFilename, $documents);
                $typology = $this->getFileTypology($enveloppeFilename, $code_nature);
                $type_pj[] = $typology;
                $type_piece_file[] = ['filename' => $enveloppeFilename, 'typologie' => $typology];
            }
        }

        $documentCreationService = $this->objectInstancier->getInstance(DocumentCreationService::class);
        $new_id_d = $documentCreationService->createDocumentWithoutAuthorizationChecking($this->id_e, self::FLUX_NAME);

        $donneesFormulaire = $this->getDonneesFormulaireFactory()->get($new_id_d);
        $donneesFormulaire->setData('acte_nature', $code_nature);
        $donneesFormulaire->setData('numero_de_lacte', $numero_interne);
        $donneesFormulaire->setData('objet', $objet);
        $donneesFormulaire->setData('date_de_lacte', $date);
        $donneesFormulaire->setData('classification', $code_matiere);

        $donneesFormulaire->addFileFromCopy(
            'arrete',
            $files[0]->filename,
            $files[0]->filepath
        );

        for ($i = 1; $i < count($files); ++$i) {
            $donneesFormulaire->addFileFromCopy(
                'autre_document_attache',
                $files[$i]->filename,
                $files[$i]->filepath,
                $i - 1
            );
        }
        $donneesFormulaire->setData('type_acte', $type_acte);
        $donneesFormulaire->setData('type_pj', json_encode($type_pj));
        $donneesFormulaire->setData('type_piece', (count($type_pj) + 1) . ' fichier(s) typé(s)');
        $donneesFormulaire->addFileFromData('type_piece_fichier', 'type_piece.json', json_encode($type_piece_file));

        $donneesFormulaire->setData('envoi_sae', true);
        $donneesFormulaire->setData('has_information_complementaire', true);

        $donneesFormulaire->addFileFromCopy(
            'aractes',
            $this->getDonneesFormulaire()->getFileName('aractes', 0),
            $this->getDonneesFormulaire()->getFilePath('aractes', 0)
        );

        $this->getDocument()->setTitre($new_id_d, $donneesFormulaire->getTitre());

        if (!$donneesFormulaire->isValidable()) {
            $message = "Le document $new_id_d créé n'est pas valide : " . $donneesFormulaire->getLastError();
            $this->changeAction('erreur', $message);
            throw new Exception($message);
        }

        $message = "[actes-preversement-seda] Passage en importation";
        $this->getActionCreator($new_id_d)->addAction(
            $this->id_e,
            0,
            'importation',
            $message
        );
        $this->getJobManager()->setJobForDocument($this->id_e, $new_id_d, $message);
        $this->addActionOK("Création du document Pastell $new_id_d");
        return true;
    }

    public function getJobManager(): JobManager
    {
        return $this->objectInstancier->getInstance(JobManager::class);
    }


    /**
     * @param SimpleXMLElement $attributes
     * @param $name
     * @return string
     * @throws Exception
     */
    private function retrieveAttributes(SimpleXMLElement $attributes, $name)
    {
        if (empty($attributes->$name)) {
            throw new Exception("Impossible de trouver l'attribut $name dans le fichier XML");
        }
        return strval($attributes->$name);
    }

    /**
     * @param SimpleXMLElement $xml
     * @param $child_tag
     * @return string
     * @throws Exception
     */
    public function retrieveChildrenContent(SimpleXMLElement $xml, $child_tag)
    {
        if (empty($xml->$child_tag)) {
            throw new Exception("Impossible de trouver l'élement $child_tag");
        }
        return strval($xml->$child_tag);
    }

    /**
     * @param string $enveloppeFilename
     * @param $documents
     * @return Fichier
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function getFileFromEnveloppe(string $enveloppeFilename, $documents): Fichier
    {
        $file = new Fichier();
        if (!in_array($enveloppeFilename, $documents)) {
            throw new UnrecoverableException(
                sprintf("Aucun fichier ayant comme nom « %s » n'a été trouvé", $enveloppeFilename)
            );
        }
        $file->filename = $enveloppeFilename;
        $file->filepath = $this->getDonneesFormulaire()->getFilePath(
            'document',
            array_search($file->filename, $documents)
        );

        return $file;
    }

    /**
     * @param string $enveloppeFilename
     * @param string $code_nature
     * @return string
     * @throws UnrecoverableException
     */
    private function getFileTypology(
        string $enveloppeFilename,
        string $code_nature
    ): string {

        /** @var DonneesFormulaire $connecteurData */
        $connecteurData = $this->objectInstancier
            ->getInstance(ConnecteurFactory::class)
            ->getConnecteurConfigByType($this->id_e, self::FLUX_NAME, 'TdT');

        preg_match('/^(.*)-(.*)-(.*)-(.*)-(.*)-(.*)-(\d-\d)_(.*)$/U', $enveloppeFilename, $matches);

        if ($matches) {
            return $matches[1];
        }

        $connecteur = $this->objectInstancier
            ->getInstance(ConnecteurFactory::class)
            ->getConnecteurByType($this->id_e, self::FLUX_NAME, 'TdT');

        if (! $connecteur) {
            return "99_AU";
        }
        /** @var TdtConnecteur $connecteur */
        return $connecteur->getDefaultTypology($code_nature, $connecteurData->getFilePath('classification_file'));
    }
}
