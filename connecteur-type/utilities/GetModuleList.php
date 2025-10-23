<?php

use Pastell\Service\Module\ModuleListService;

class GetModuleList extends ConnecteurTypeChoiceActionExecutor
{
    private const MODULE_TYPE_FIELD = 'module_type';
    private const MODULE_TYPE_LABEL_FIELD = 'module_type_label';
    private const PAGE_TITLE = 'page_title';

    /**
     * @return bool
     * @throws RecoverableException
     */
    public function go(): bool
    {
        $moduleType = (string)$this->getRecuperateur()->get(self::MODULE_TYPE_FIELD);
        $moduleList = $this->displayAPI();
        if ($moduleType && empty($moduleList[$moduleType])) {
            throw new RecoverableException("Ce type de dossier n'existe pas");
        }
        $this->getConnecteurProperties()->setData(
            $this->getMappingValue(self::MODULE_TYPE_FIELD),
            $moduleType
        );
        $this->getConnecteurProperties()->setData(
            $this->getMappingValue(self::MODULE_TYPE_LABEL_FIELD),
            $moduleList[$moduleType]['nom']
        );
        return true;
    }

    /**
     * @throws NotFoundException
     */
    public function display(): true
    {
        $this->setViewParameter(
            'moduleType',
            $this->getConnecteurProperties()->get($this->getMappingValue(self::MODULE_TYPE_FIELD))
        );

        $this->setViewParameter('moduleList', $this->displayAPI());
        $this->renderPage(
            $this->getMappingValue(self::PAGE_TITLE),
            'connectorType/utilities/GetModuleList'
        );
        return true;
    }

    public function displayAPI(): array
    {
        return $this->objectInstancier->getInstance(ModuleListService::class)->getModuleListOrderByNom($this->id_u);
    }
}
