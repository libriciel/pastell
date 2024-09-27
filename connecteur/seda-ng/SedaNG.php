<?php

/**
 * @deprecated 4.1.0, use SedaGenerator instead, to be removed in v5
 */
class SedaNG extends SEDAConnecteur
{
    public const CONNECTEUR_ID = 'seda-ng';

    /** @var  DonneesFormulaire */
    private $connecteurConfig;

    private $last_validation_error;

    /**
     * @param DonneesFormulaire $connecteurConfig
     */
    public function setConnecteurConfig(DonneesFormulaire $connecteurConfig)
    {
        $this->connecteurConfig = $connecteurConfig;
    }

    public function getLastValidationError(): array
    {
        return $this->last_validation_error;
    }

    /**
     * @return string
     */
    private function getTransferIdentifier()
    {
        $last_date = $this->connecteurConfig->get("date_dernier_transfert");
        $numero_transfert = (int)$this->connecteurConfig->get("dernier_numero_transfert");

        $date = date('Y-m-d');
        if ($last_date == $date) {
            $numero_transfert++;
        } else {
            $numero_transfert = 1;
        }

        $this->connecteurConfig->setData('date_dernier_transfert', $date);
        $this->connecteurConfig->setData('dernier_numero_transfert', $numero_transfert);

        return $date . "-" . $numero_transfert;
    }

    /**
     * @return null|string|string[]
     * @throws Exception
     */
    public function getBordereauTest()
    {
        $flux_info = $this->connecteurConfig->getFileContent('flux_info_content');
        $data = [];
        if ($flux_info) {
            foreach (json_decode($flux_info, true) as $key => $value) {
                $data[$key] = $value;
            }
        }

        $fluxDataTest = new FluxDataTest($data);

        return $this->getBordereau($fluxDataTest);
    }

    /**
     * @throws Exception
     */
    public function getBordereau(FluxData $fluxData): string
    {
        $relax_ng_path = $this->getSchemaRngPath();
        $agape_file_path = $this->getAgapeFilePath();

        $relaxNGImportAgapeAnnotation = new RelaxNgImportAgapeAnnotation();
        $relaxNG_with_annotation = $relaxNGImportAgapeAnnotation->importAnnotation($relax_ng_path, $agape_file_path);

        $generateXMLFromAnnotedRelaxNG = new GenerateXMLFromAnnotedRelaxNG(new RelaxNG());
        $bordereau_seda_with_annotation = $generateXMLFromAnnotedRelaxNG->generateFromRelaxNGString(
            $relaxNG_with_annotation
        );

        $connecteur_info = $this->connecteurConfig->getFileContent('connecteur_info_content');
        $data = [];
        if ($connecteur_info) {
            foreach (json_decode($connecteur_info, true) as $key => $value) {
                $data[$key] = $value;
            }
        }

        $annotationWrapper = new AnnotationWrapper();
        $annotationWrapper->setConnecteurInfo($data);
        $fluxData->setConnecteurContent($data);
        $annotationWrapper->setFluxData($fluxData);
        $annotationWrapper->setTranslitFilenameRegExp($this->connecteurConfig->get('translit_filename'));

        $annotationWrapper->setCompteurJour($this->getTransferIdentifier());
        $generateBordereauSEDA = new GenerateBordereauSEDA();
        $xml = $generateBordereauSEDA->generate($bordereau_seda_with_annotation, $annotationWrapper);

        return $xml;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getSchemaRngPath()
    {
        $relax_ng_path = $this->connecteurConfig->getFilePath('schema_rng');
        if (!file_exists($relax_ng_path)) {
            throw new Exception("Le profil SEDA (RelaxNG) n'a pas été trouvé.");
        }
        return $relax_ng_path;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getAgapeFilePath()
    {
        $agape_file_path = $this->connecteurConfig->getFilePath('profil_agape');

        if (!file_exists($agape_file_path)) {
            throw new Exception("Le profil SEDA (fichier Agape) n'a pas été trouvé.");
        }
        return $agape_file_path;
    }

    /**
     * @param string $bordereau
     * @return bool
     * @throws Exception
     */
    public function validateBordereau(string $bordereau): bool
    {
        $relax_ng_path = $this->getSchemaRngPath();
        $sedaValidation = new SedaValidation($this->getDataDir());
        if (!$sedaValidation->validateRelaxNG($bordereau, $relax_ng_path)) {
            $this->last_validation_error = $sedaValidation->getLastErrors();
            throw new Exception("Erreur lors de la validation du bordereau (validation du schéma RelaxNG)");
        }

        if (!$sedaValidation->validateSEDA($bordereau)) {
            $this->last_validation_error = $sedaValidation->getLastErrors();
            throw new Exception("Erreur lors de la validation du bordereau (validation du schéma SEDA)");
        }
        return true;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getProprietePastellFlux()
    {
        $result = $this->getProprietePastell('flux');
        return array_merge($result, $this->getProprietePastell('file'));
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getProprietePastellConnecteur()
    {
        return array_merge(
            $this->getProprietePastell('connecteur'),
            $this->getProprietePastell('connecteurInfo')
        );
    }

    /**
     * @param $type
     * @return array
     * @throws Exception
     */
    public function getProprietePastell($type)
    {
        $agape_file_path = $this->getAgapeFilePath();

        $agapeFile = new AgapeFile();

        $annotation_list = $agapeFile->getAllAnnotation($agape_file_path);
        $annotationWrapper = new AnnotationWrapper();
        $result = [];
        foreach ($annotation_list as $annotation) {
            $result = array_merge($result, $annotationWrapper->extractInfo($annotation));
        }
        $the_result = [];
        foreach ($result as $command_info) {
            [$command,$data] = $command_info;
            if ($command == $type) {
                $the_result[] = $data;
            }
        }
        return $the_result;
    }

    /**
     * @throws Exception
     */
    public function generateArchive(FluxData $fluxData, string $archive_path): void
    {
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        try {
            $this->generateArchiveThrow($fluxData, $archive_path, $tmp_folder);
        } finally {
            $tmpFolder->delete($tmp_folder);
        }
    }
}
