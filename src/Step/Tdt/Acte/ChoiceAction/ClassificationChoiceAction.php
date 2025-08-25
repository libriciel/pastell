<?php

declare(strict_types=1);

namespace Pastell\Step\Tdt\Acte\ChoiceAction;

use ClassificationActes;
use ConnecteurTypeChoiceActionExecutor;
use Exception;
use NotFoundException;
use Recuperateur;
use UnrecoverableException;

class ClassificationChoiceAction extends ConnecteurTypeChoiceActionExecutor
{
    /**
     * @throws NotFoundException
     */
    public function go(): void
    {
        $recuperateur = new Recuperateur($_GET);
        $classif = $recuperateur->get('classif');
        $this->getDonneesFormulaire()->setData('classification', $classif);
    }

    /**
     * @throws UnrecoverableException
     */
    public function displayAPI(): array
    {
        return $this->getClassificationActes()->getAll();
    }

    /**
     * @throws NotFoundException
     */
    public function display(): bool
    {
        $this->setViewParameter('classificationActes', $this->getClassificationActes());
        $this->renderPage(
            'Choix de la classification en matière et sous matière',
            'module/actes/ChoixClassification'
        );
        return true;
    }

    /**
     * @throws UnrecoverableException
     */
    private function getClassificationActes(): ClassificationActes
    {
        $donneesFormulaire = $this->getConnecteurFactory()->getConnecteurConfigByType($this->id_e, $this->type, 'TdT');
        if (!$donneesFormulaire) {
            throw new Exception("La classification en matière et sous-matière n'est pas disponible");
        }
        $file = $donneesFormulaire->getFilePath('classification_file');
        if (!file_exists($file)) {
            throw new Exception("La classification en matière et sous-matière n'est pas disponible ($file)");
        }
        return new ClassificationActes($donneesFormulaire->getFilePath('classification_file'));
    }
}
