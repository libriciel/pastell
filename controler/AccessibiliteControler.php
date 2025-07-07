<?php

declare(strict_types=1);

class AccessibiliteControler extends PastellControler
{
    private const SCHEMA_PLURIANNUEL_PATH =
        PASTELL_PATH . '/data/_shared/LIBRICIEL-SCOP_schema_pluriannuel_accessibilité_06-25.pdf';
    private const DECLARATION_PATH =
        PASTELL_PATH . '/data/_shared/declaration-accessibilite-pastell.pdf';

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
        $this->setViewParameter('page_title', "Accessibilité");
        $this->setViewParameter('twigTemplate', 'accessibilite/index.html.twig');
        $this->setViewParameter('schema_pluriannuel_path', self::SCHEMA_PLURIANNUEL_PATH);
        $this->setViewParameter('declaration_path', self::DECLARATION_PATH);
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
