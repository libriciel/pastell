<?php

declare(strict_types=1);

namespace Pastell\Viewer;

use ConnecteurFactory;
use DonneesFormulaireFactory;
use Exception;
use ObjectInstancier;
use RecoverableException;

final class ViewerFactory
{
    public function __construct(
        private readonly ObjectInstancier $objectInstancier,
    ) {
    }

    /**
     * @throws Exception
     */
    public function display(string $id_d, string $field, int $num = 0): void
    {
        $donneesFormulaire = $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->get($id_d);

        $filename = $donneesFormulaire->getFileName($field, $num);
        $filepath = $donneesFormulaire->getFilePath($field, $num);

        $visionneuseClassName = $donneesFormulaire->getFormulaire()->getField($field)->getVisionneuse();
        $visionneuse = $this->getViewer($visionneuseClassName);

        $visionneuse->display($filename, $filepath);
    }

    /**
     * @throws Exception
     */
    public function displayConnecteur(int $id_ce, string $field, int $num = 0): void
    {
        $donneesFormulaire = $this->objectInstancier->getInstance(
            DonneesFormulaireFactory::class
        )->getConnecteurEntiteFormulaire($id_ce);
        $filename = $donneesFormulaire->getFileName($field, $num);
        $filepath = $donneesFormulaire->getFilePath($field, $num);

        $viewerClassName = $donneesFormulaire->getFormulaire()->getField($field)->getVisionneuse();
        $viewer = $this->getViewer($viewerClassName);
        if ($viewer instanceof ConnectorViewer) {
            $viewer->setConnector(
                $this->objectInstancier->getInstance(ConnecteurFactory::class)->getConnecteurById($id_ce)
            );
        }

        $viewer->display($filename, $filepath);
    }

    /**
     * @throws RecoverableException
     * @throws Exception
     */
    private function getViewer(string $viewerClassName): Viewer
    {
        if (!$viewerClassName) {
            throw new RecoverableException("Le champs ne dispose pas d'une visionneuse");
        }

        if (!\class_exists($viewerClassName)) {
            throw new RecoverableException("La classe $viewerClassName n'a pas été trouvée.");
        }

        $viewer = $this->objectInstancier->newInstance($viewerClassName);

        if (!$viewer instanceof Viewer) {
            throw new \RecoverableException(
                \sprintf(
                    'The class %s needs to implements : %s',
                    $viewerClassName,
                    Viewer::class
                )
            );
        }
        return $viewer;
    }
}
