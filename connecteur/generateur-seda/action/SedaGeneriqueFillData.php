<?php

declare(strict_types=1);

use Pastell\Connector\AbstractSedaGeneratorConnector;
use Pastell\Service\Connecteur\ConnecteurAssociationService;

class SedaGeneriqueFillData extends ChoiceActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        /** @var AbstractSedaGeneratorConnector $connector */
        $connector = $this->getMyConnecteur();
        $pastell_to_seda = $connector->getPastellToSeda();

        $data = $this->getConnecteurConfig($this->id_ce)->getFileContent('data');
        $json = json_decode($data ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        foreach ($pastell_to_seda as $pastell_id => $element_info) {
            $json[$pastell_id] = $this->getRecuperateur()->get($pastell_id);
        }
        $json['keywords'] = $this->getRecuperateur()->get('keywords');

        $this->getConnecteurConfig($this->id_ce)->addFileFromData(
            'data',
            "data.json",
            json_encode($json, JSON_THROW_ON_ERROR)
        );

        return true;
    }

    /**
     * @throws JsonException
     */
    public function display()
    {
        $flux = $this->objectInstancier->getInstance(ConnecteurAssociationService::class)
            ->getUniqueAssociatedFlux((int)$this->id_ce, (int)$this->id_e);
        $this->setViewParameter('flux', $flux);
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($flux);
        $this->setViewParameter('fieldsList', $documentType->getFormulaire()->getFieldsList());

        $json = $this->getConnecteurConfig($this->id_ce)->getFileContent('data');
        if ($json === false) {
            $json = '{}';
        }
        $this->setViewParameter('data', json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        /** @var AbstractSedaGeneratorConnector $connector */
        $connector = $this->getMyConnecteur();
        $this->setViewParameter('pastell_to_seda', $connector->getPastellToSeda());

        $this->renderPage(
            'Sélection des métadonnées du bordereau',
            'connector/sedaGenerator/SedaGeneriqueFillData'
        );
        return true;
    }

    public function displayAPI()
    {
        // TODO: Implement displayAPI() method.
    }
}
