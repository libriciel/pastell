<?php

declare(strict_types=1);

use Pastell\Connector\AbstractSedaGeneratorConnector;
use Pastell\Service\Connecteur\ConnecteurAssociationService;

class SedaGeneriqueFillFiles extends ChoiceActionExecutor
{
    /**
     * @return bool
     * @throws SimpleXMLWrapperException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go()
    {
        $node_id = $this->getRecuperateur()->get('node_id');

        $files_content = $this->getConnecteurConfig($this->id_ce)->getFileContent('files') ?: '';

        $generateurSedaFillFiles = new GenerateurSedaFillFiles($files_content);

        foreach ($generateurSedaFillFiles->getFiles($node_id) as $files) {
            $generateurSedaFillFiles->setNodeDoNotPutMineType((string)$files['id'], false);
        }

        foreach ($this->getRecuperateur()->getAll() as $key => $value) {
            if (preg_match("#^description_(.*)$#", $key, $matches)) {
                $generateurSedaFillFiles->setNodeDescription($matches[1], $value);
            }
            if (preg_match("#^expression_(.*)$#", $key, $matches)) {
                $generateurSedaFillFiles->setNodeExpression($matches[1], $value);
            }
            if (preg_match("#^do_not_put_mime_type_(.*)$#", $key, $matches)) {
                $generateurSedaFillFiles->setNodeDoNotPutMineType($matches[1], true);
            }
        }
        if ($node_id) {
            $specififInfoArray = [];
            foreach (array_keys($generateurSedaFillFiles->getArchiveUnitSpecificInfoDefinition()) as $specificInfoID) {
                $specifInfoValue = $this->getRecuperateur()->get($specificInfoID);
                $specififInfoArray[$specificInfoID] = $specifInfoValue;
            }
            $generateurSedaFillFiles->setArchiveUnitInfo($node_id, $specififInfoArray);
        }

        if ($this->getRecuperateur()->get('add-file') === 'root') {
            $generateurSedaFillFiles->addFile($node_id);
        }
        if ($this->getRecuperateur()->get('delete-file')) {
            $generateurSedaFillFiles->deleteNode($this->getRecuperateur()->get('delete-file'));
        }
        if ($this->getRecuperateur()->get('add-unit') === 'root') {
            $generateurSedaFillFiles->addArchiveUnit($node_id);
        }
        if ($this->getRecuperateur()->get('delete-unit')) {
            $generateurSedaFillFiles->deleteNode($this->getRecuperateur()->get('delete-unit'));
        }

        if ($this->getRecuperateur()->get('up')) {
            $generateurSedaFillFiles->upNode($this->getRecuperateur()->get('up'));
        }
        if ($this->getRecuperateur()->get('down')) {
            $generateurSedaFillFiles->downNode($this->getRecuperateur()->get('down'));
        }

        $this->getConnecteurConfig($this->id_ce)->addFileFromData(
            'files',
            "files.xml",
            $generateurSedaFillFiles->getXML()
        );

        if ($this->getRecuperateur()->get('node_id_to')) {
            $node_id = $this->getRecuperateur()->get('node_id_to');
            if ($node_id === 'root') {
                $node_id = '';
            }
            $this->redirect("/Connecteur/externalData?id_ce={$this->id_ce}&field=fill_files&node_id=$node_id");
        }
        if ($this->getRecuperateur()->get('unit-content')) {
            $node_id = $this->getRecuperateur()->get('unit-content');
            $this->redirect("/Connecteur/externalData?id_ce={$this->id_ce}&field=fill_files&node_id=$node_id");
        }
        if (!$this->getRecuperateur()->get('enregistrer')) {
            $this->redirect("/Connecteur/externalData?id_ce={$this->id_ce}&field=fill_files&node_id=$node_id");
        }
        return true;
    }

    /**
     * @throws SimpleXMLWrapperException
     * @throws Exception
     */
    public function display()
    {
        $this->setViewParameter('node_id', $this->getRecuperateur()->get('node_id'));
        $flux = $this->objectInstancier->getInstance(ConnecteurAssociationService::class)
            ->getUniqueAssociatedFlux((int)$this->id_ce, (int)$this->id_e);
        $this->setViewParameter('flux', $flux);
        $documentType = $this->getDocumentTypeFactory()->getFluxDocumentType($flux);
        $this->setViewParameter('fieldsList', $documentType->getFormulaire()->getFieldsList());

        $files = $this->getConnecteurConfig($this->id_ce)->getFileContent('files');
        if ($files === false) {
            $files = '';
        }

        $this->setViewParameter('generateurSedaFillFiles', new GenerateurSedaFillFiles($files));

        /** @var AbstractSedaGeneratorConnector $connector */
        $connector = $this->getMyConnecteur();
        $this->setViewParameter('pastell_to_seda', $connector->getPastellToSeda());

        $this->renderPage(
            "Gestion des fichiers de l'archive",
            'connector/sedaGenerator/SedaGeneriqueFillFiles'
        );
        return true;
    }

    public function displayAPI()
    {
        // TODO: Implement displayAPI() method.
    }
}
