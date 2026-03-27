<?php

declare(strict_types=1);

use Pastell\Actes\ActeEnvelopeParser;

class ActesPreversementSEDACreate extends ActionExecutor
{
    public const string FLUX_NAME_NEW_DOC = 'ls-actes-tdt-versant-sae';

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go(): bool
    {
        $envelopeParser = new ActeEnvelopeParser(
            $this->getDonneesFormulaire()->getFileContent('enveloppe_metier')
        );
        $aractesParser = new ActeEnvelopeParser(
            $this->getDonneesFormulaire()->getFileContent('aractes')
        );

        /** @var Fichier[] $files */
        $files = [];
        $documents = $this->getDonneesFormulaire()->get('document');
        $acteFilename = $envelopeParser->getActeFilename();
        $files[] = $this->getFileFromEnveloppe($acteFilename, $documents);
        for ($i = 0; $i < $envelopeParser->getAnnexesNumber(); ++$i) {
            $annexeFilename = $envelopeParser->getAnnexeFilename($i);
            $files[] = $this->getFileFromEnveloppe($annexeFilename, $documents);
        }

        $documentCreationService = $this->objectInstancier->getInstance(DocumentCreationService::class);
        $documentId = $documentCreationService->createDocumentWithoutAuthorizationChecking(
            $this->id_e,
            self::FLUX_NAME_NEW_DOC
        );

        $recuperateur = new Recuperateur([
            'acte_nature' => $envelopeParser->getCodeNatureActe(),
            'numero_de_lacte' => $envelopeParser->getNumeroInterne(),
            'objet' => $envelopeParser->getObjet(),
            'date_de_lacte' => $envelopeParser->getDate(),
            'document_papier' => $envelopeParser->hasDocumentPapier(),
            'classification' => $envelopeParser->getClassification(),
            'date_ar' => $aractesParser->getDateAr(),
        ]);
        $documentModificationService = $this->objectInstancier->getInstance(DocumentModificationService::class);
        $documentModificationService->modifyDocumentWithoutAuthorizationChecking(
            $this->id_e,
            0,
            $documentId,
            $recuperateur,
            new \FileUploader(),
            true
        );

        $donneesFormulaireNewDoc = $this->getDonneesFormulaireFactory()->get($documentId);
        $donneesFormulaireNewDoc->addFileFromCopy(
            'arrete',
            $files[0]->filename,
            $files[0]->filepath
        );
        for ($i = 1, $iMax = count($files); $i < $iMax; ++$i) {
            $donneesFormulaireNewDoc->addFileFromCopy(
                'autre_document_attache',
                $files[$i]->filename,
                $files[$i]->filepath,
                $i - 1
            );
        }
        $donneesFormulaireNewDoc->addFileFromCopy(
            'aractes',
            $this->getDonneesFormulaire()->getFileName('aractes', 0),
            $this->getDonneesFormulaire()->getFilePath('aractes', 0)
        );

        $this->getDocument()->setTitre($documentId, $donneesFormulaireNewDoc->getTitre());

        $donneesFormulaireNewDoc = $this->getDonneesFormulaireFactory()->get($documentId);
        if (!$donneesFormulaireNewDoc->isValidable()) {
            $message = "Le document $documentId créé n'est pas valide : " . $donneesFormulaireNewDoc->getLastError();
            $this->changeAction('erreur', $message);
            throw new Exception($message);
        }

        $message = '[actes-preversement-seda] Passage en importation';
        $this->getActionChange()->addAction(
            $documentId,
            $this->id_e,
            0,
            'importation',
            $message
        );
        $this->objectInstancier->getInstance(JobManager::class)
            ->setJobForDocument($this->id_e, $documentId, $message);
        $this->addActionOK("Création du document Pastell $documentId");
        return true;
    }

    /**
     * @throws NotFoundException
     * @throws UnrecoverableException
     */
    private function getFileFromEnveloppe(string $enveloppeFilename, array $documents): Fichier
    {
        $file = new Fichier();
        if (!in_array($enveloppeFilename, $documents, true)) {
            throw new UnrecoverableException(
                sprintf("Aucun fichier ayant comme nom « %s » n'a été trouvé", $enveloppeFilename)
            );
        }
        $file->filename = $enveloppeFilename;
        $file->filepath = $this->getDonneesFormulaire()->getFilePath(
            'document',
            array_search($file->filename, $documents, true)
        );

        return $file;
    }
}
