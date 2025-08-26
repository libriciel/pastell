<?php

declare(strict_types=1);

class AccessibiliteControler extends PastellControler
{
    public function _beforeAction(): void
    {
        parent::_beforeAction();
        $this->setViewParameter('pages_without_left_menu', true);
        $this->setViewParameter('dont_display_breacrumbs', true);
    }

    /**
     * @throws NotFoundException
     */
    public function indexAction(): void
    {
        $data_dir = $this->getObjectInstancier()->getInstance('data_dir');
        $schema_pluriannuel_path = $data_dir . '/_shared/LIBRICIEL-SCOP_schema_pluriannuel_accessibilité_06-25.pdf';
        $declaration_path = $data_dir . '/_shared/declaration-accessibilite-pastell.pdf';

        $this->setViewParameter('page_title', 'Accessibilité');
        $this->setViewParameter('twigTemplate', 'accessibilite/index.html.twig');
        $this->setViewParameter('schema_pluriannuel_path', $schema_pluriannuel_path);
        $this->setViewParameter('declaration_path', $declaration_path);
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    public function getFileAction(): void
    {
        $filePath = $this->getPostInfo()->get('file_path');
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new NotFoundException("Le fichier $filePath n'existe pas ou n'est pas accessible en lecture");
        }
        $sendFileToBrowser = $this->getObjectInstancier()->getInstance(SendFileToBrowser::class);
        $sendFileToBrowser->send($filePath);

        $this->renderDefault();
    }
}
