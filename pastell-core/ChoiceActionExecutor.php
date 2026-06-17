<?php

use Pastell\Service\Menu\MenuGaucheOption;
use Pastell\Service\Menu\MenuGaucheService;
use Twig\Environment;

abstract class ChoiceActionExecutor extends ActionExecutor
{
    private array $viewParameter;
    protected string $field;
    protected int $page = 0;

    private $recuperateur;


    public function __construct(ObjectInstancier $objectInstancier)
    {
        parent::__construct($objectInstancier);
        $this->viewParameter = [];
        $this->setRecuperateur(new Recuperateur($_POST));
    }

    public function setRecuperateur(Recuperateur $recuperateur)
    {
        $this->recuperateur = $recuperateur;
    }

    public function getRecuperateur(): Recuperateur
    {
        return $this->recuperateur;
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function setViewParameter($key, $value)
    {
        $this->viewParameter[$key] = $value;
    }

    public function getViewParameter(): array
    {
        $this->viewParameter['id_d'] = $this->id_d;
        $this->viewParameter['id_e'] = $this->id_e;
        $this->viewParameter['id_ce'] = $this->id_ce;
        $this->viewParameter['action'] = $this->action;
        $this->viewParameter['field'] = $this->field;
        $this->viewParameter['page'] = $this->page;
        return $this->viewParameter;
    }

    /**
     * @throws NotFoundException
     */
    public function renderPage(string $pageTitle, string $template): void
    {
        $this->setViewParameter('page_title', $pageTitle);
        $this->setViewParameter('template_milieu', $template);
        $pastellController = $this->objectInstancier->getInstance(PastellControler::class);
        $pastellController->setAllViewParameter($this->getViewParameter());
        $this->resolveMenuGauche($pastellController);
        $pastellController->setTwigEnvironment($this->objectInstancier->getInstance(Environment::class));
        $pastellController->renderDefault();
    }

    /**
     * @throws NotFoundException
     */
    private function resolveMenuGauche(PastellControler $pastellController): void
    {
        if ($this->id_ce) {
            $pastellController->setViewParameter('id_e_menu', $this->id_e);
            $pastellController->setViewParameter('type_e_menu', '');

            $global_connector = $this->isGlobalConnecteur($this->id_ce);
            $pastellController->setEntiteMenuGauche((int) $this->id_e);
            $pastellController->setNavigationInfo($this->id_e, "/Entite/connecteur?global=$global_connector");
            $pastellController->setMenuGaucheSelect(
                $global_connector ?
                    MenuGaucheService::ENTITE_CONNECTEUR_GLOBAL :
                    MenuGaucheService::ENTITE_CONNECTEUR_LOCAL
            );
            return;
        }

        if ($this->id_d) {
            $documentInfo = $this->objectInstancier->getInstance(DocumentSQL::class)->getInfo($this->id_d);
            if ($documentInfo) {
                $pastellController->setNavigationInfo($this->id_e, "Document/list?type=$this->type");
                $pastellController->setMenuGaucheSelect(MenuGaucheOption::buildUrl(MenuGaucheService::DOCUMENT_LIST, ['type' => $this->type]));
                $pastellController->setDocumentMenuGauche((int) $this->id_e);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function redirectToFormulaire(): never
    {
        $url = sprintf(
            '%s/Document/edition?id_d=%s&id_e=%s&page=%s',
            $this->getSiteBase(),
            $this->id_d,
            $this->id_e,
            $this->page
        );
        header("Location: $url");
        exit_wrapper();
    }

    /**
     * @throws Exception
     */
    public function redirectToConnecteurFormulaire(): void
    {
        $url = sprintf(
            '%s/Connecteur/editionModif?id_ce=%s',
            $this->getSiteBase(),
            $this->id_ce
        );
        header_wrapper("Location: $url");
        exit_wrapper();
    }

    public function isEnabled()
    {
        return true;
    }

    protected function getConnecteurTypeActionExecutor()
    {
        $connecteurTypeActionExecutor =  parent::getConnecteurTypeActionExecutor();
        /**
         * bof...
         * @var ConnecteurTypeChoiceActionExecutor $connecteurTypeActionExecutor
         */
        $connecteurTypeActionExecutor->field = $this->field;
        $connecteurTypeActionExecutor->page = $this->page;
        $connecteurTypeActionExecutor->setRecuperateur($this->getRecuperateur());
        return $connecteurTypeActionExecutor;
    }

    abstract public function display();

    abstract public function displayAPI();

    /** Permet d'afficher une liste pour la recherche avancée */
    public function displayChoiceForSearch()
    {
        return [];
    }
}
