<?php

class Gabarit
{
    private array $viewParameter;

    public function __construct(
        private ObjectInstancier $objectInstancier,
        private string $template_path,
    ) {
        $this->viewParameter = [];
    }

    public function setViewParameter($key, $value)
    {
        $this->viewParameter[$key] = $value;
    }

    public function setParameters(array $parameter)
    {
        $this->viewParameter = array_merge($this->viewParameter, $parameter);
    }

    protected function getAPIController($controllerName)
    {
        $baseAPIControllerFactory = $this->objectInstancier->getInstance(BaseAPIControllerFactory::class);
        return $baseAPIControllerFactory->getInstance(
            $controllerName,
            $this->objectInstancier->getInstance(Authentification::class)->getId()
        );
    }


    public function getRender(string $template): string
    {
        ob_start();
        $this->render($template);
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }

    /**
     * Affiche un template en mettant à sa disposition toutes les variables trouvé dans le tableau de paramètre
     *
     *
     * @param string $template Ce paramètre est soit un chemin vers un fichier avec l'extension PHP, soit un nom de template sans extension et sans chemin
     *                          qui sera chercher dans template_path et auquel on ajoutera l'extension .php
     */
    public function render($template)
    {
        foreach ($this->viewParameter as $key => $value) {
            $$key = $value;
        }
        if (preg_match("#\.php$#", $template)) {
            include($template);
        } else {
            include("{$this->template_path}/$template.php");
        }
    }

    public function templateExists($template)
    {
        return file_exists("{$this->template_path}/$template.php");
    }

    public function __get($key)
    {
        if (isset($this->viewParameter[$key])) {
            return $this->viewParameter[$key];
        }
        return $this->objectInstancier->getInstance($key);
    }

    public function suivantPrecedent($offset, $limit, $nb_total, $link = null, $message = null)
    {
        if (! $message) {
            $message = 'Position %1$s à %2$s sur %3$s';
        }

        if (! $link) {
            $link = $_SERVER['PHP_SELF'];
        }
        if (strstr($link, "?")) {
             $link = $link . "&";
        } else {
             $link = $link . "?";
        }
        include("{$this->template_path}/SuivantPrecedent.php");
    }

    public function url(string $route = ''): void
    {
        echo $this->getSiteBase() . '/' . ltrim($route, '/');
    }

    public function url_mailsec($route = "")
    {
        echo rtrim(WEBSEC_BASE, "/") . "/" . ltrim($route, "/");
    }

    public function urlWithBuildNumber($url)
    {
        /** @var ManifestFactory $manifestFactory */
        $manifestFactory = $this->objectInstancier->getInstance(ManifestFactory::class);
        $this->url($url . "?build=" . $manifestFactory->getPastellManifest()->getRevision());
    }

    /**
     * @return CSRFToken
     */
    public function getCSRFToken()
    {
        return $this->objectInstancier->getInstance(CSRFToken::class);
    }

    public function displayCSRFInput()
    {
        $this->getCSRFToken()->displayFormInput();
    }

    public function getLastError(): LastError
    {
        return $this->objectInstancier->getInstance(LastError::class);
    }
    public function getLastMessage(): LastMessage
    {
        return $this->objectInstancier->getInstance(LastMessage::class);
    }

    public function getHTMLPurifier(): HTMLPurifier
    {
        return $this->objectInstancier->getInstance(HTMLPurifier::class);
    }

    public function getRechercheAvanceFormulaireHTML(): RechercheAvanceFormulaireHTML
    {
        return $this->objectInstancier->getInstance(RechercheAvanceFormulaireHTML::class);
    }

    public function getDocumentTypeHtml(): DocumentTypeHTML
    {
        return $this->objectInstancier->getInstance(DocumentTypeHTML::class);
    }

    public function getFancyDate(): FancyDate
    {
        return $this->objectInstancier->getInstance(FancyDate::class);
    }

    public function getAuthentification(): Authentification
    {
        return $this->objectInstancier->getInstance(Authentification::class);
    }

    public function getDocumentTypeFactory(): DocumentTypeFactory
    {
        return $this->objectInstancier->getInstance(DocumentTypeFactory::class);
    }

    public function getRoleUtilisateur(): RoleUtilisateur
    {
        return $this->objectInstancier->getInstance(RoleUtilisateur::class);
    }

    public function getPastellTimer(): PastellTimer
    {
        return $this->objectInstancier->getInstance(PastellTimer::class);
    }

    public function getJournal(): Journal
    {
        return $this->objectInstancier->getInstance(Journal::class);
    }

    public function getDocumentIndexSql(): DocumentIndexSQL
    {
        return $this->objectInstancier->getInstance(DocumentIndexSQL::class);
    }

    public function getSiteBase(): string
    {
        return $this->objectInstancier->getInstance('site_base');
    }
}
