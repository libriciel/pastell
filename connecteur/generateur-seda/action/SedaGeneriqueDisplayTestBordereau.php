<?php

use Pastell\Connector\AbstractSedaGeneratorConnector;
use Pastell\Service\Connecteur\ConnecteurAssociationService;
use Symfony\Component\Uid\Uuid;

class SedaGeneriqueDisplayTestBordereau extends ActionExecutor
{
    /**
     * @return bool
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        /** @var AbstractSedaGeneratorConnector $sedaGenerique */
        $sedaGenerique = $this->getMyConnecteur();

        $fluxDataTest = new FluxDataTestSedaGenerique();

        $connecteurConfig = $this->getConnecteurConfig($this->id_ce);

        $data = $connecteurConfig->getFileContent('data');
        $data = json_decode($data, true);


        $file_list = [];

        $files = simplexml_load_string($connecteurConfig->getFileContent('files'));
        if ($files) {
            $files = $files->xpath("//File");
            foreach ($files as $file) {
                $file_list[] = strval($file['field_expression']);
            }
        }

        foreach ($file_list as $file => $value) {
            if (str_contains($value, 'ZIP')) {
                $this->setLastMessage(
                    "La génération d'un bordereau de test est impossible si celui-ci comporte un fichier ZIP"
                );
                return false;
            }
        }

        $fluxDataTest->addFileList($file_list);

        $date_list = [];
        if (isset($data['StartDate'])) {
            preg_match("#%(.*)%#", $data['StartDate'], $matches);
            $date_list[] = $matches[1] ?? "";
        }
        if (isset($data['EndDate'])) {
            preg_match("#%(.*)%#", $data['EndDate'], $matches);
            $date_list[] = $matches[1] ?? "";
        }
        $fluxDataTest->addDateList($date_list);

        $fakeDonneesFormulaire = $this->getDonneesFormulaireFactory()->getNonPersistingDonneesFormulaire();

        $flux = $this->objectInstancier->getInstance(ConnecteurAssociationService::class)
            ->getUniqueAssociatedFlux((int)$this->id_ce, (int)$this->id_e);
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($flux);

        $formulaire = $documentType->getFormulaire();
        $fields = $formulaire->getAllFields();
        foreach ($fields as $field) {
            if ($field->isFile()) {
                $fakeDonneesFormulaire->addFileFromData(
                    $field->getName(),
                    "nom du fichier pour " . $field->getName(),
                    Uuid::v4()->jsonSerialize()
                );
                if ($field->isMultiple()) {
                    $fakeDonneesFormulaire->addFileFromData(
                        $field->getName(),
                        "nom du second fichier pour " . $field->getName(),
                        Uuid::v4()->jsonSerialize(),
                        1
                    );
                }
            } elseif ($field->getType() === 'date' || in_array($field->getName(), ['date_journal_debut', 'date_cloture_journal'])) {
                $fakeDonneesFormulaire->setData($field->getName(), "1980-01-01");
            } else {
                $fakeDonneesFormulaire->setData($field->getName(), "/contenu de " . $field->getName() . "/");
            }
        }

        $sedaGenerique->setDocDonneesFormulaire($fakeDonneesFormulaire);

        $result = $sedaGenerique->getBordereau($fluxDataTest);

        if (!$result) {
            $this->setLastMessage($sedaGenerique->getLastValidationError());
            return false;
        }
        $sendFileToBrowser = $this->objectInstancier->getInstance(SendFileToBrowser::class);

        $sendFileToBrowser->sendData(
            $result,
            "bordereau.xml",
            "text/xml",
            SendFileToBrowser::CONTENT_DISPOSITION_INLINE
        );
        exit_wrapper();
    }
}
