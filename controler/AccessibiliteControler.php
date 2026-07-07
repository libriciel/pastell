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
        $this->setViewParameter('page_title', 'Accessibilité');
        $this->setViewParameter('twigTemplate', 'accessibilite/index.html.twig');
        $this->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    public function getFileAction(): void
    {
        $data_dir = $this->getObjectInstancier()->getInstance('data_dir');

        // Only a fixed set of shared files can be downloaded; the user provides a logical key, never a path.
        $filePath = match ($this->getPostOrGetInfo()->get('file')) {
            'schema_pluriannuel' => $data_dir . '/_shared/LIBRICIEL-SCOP_schema_pluriannuel_accessibilité_06-25.pdf',
            'declaration' => $data_dir . '/_shared/declaration-accessibilite-pastell.pdf',
            default => throw new NotFoundException('Fichier inconnu'),
        };

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            throw new NotFoundException("Le fichier n'existe pas ou n'est pas accessible en lecture");
        }

        $sendFileToBrowser = $this->getObjectInstancier()->getInstance(SendFileToBrowser::class);
        $sendFileToBrowser->send($filePath);

        $this->renderDefault();
    }
}
