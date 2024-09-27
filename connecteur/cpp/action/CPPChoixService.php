<?php

class CPPChoixService extends ChoiceActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        $recuperateur = $this->getRecuperateur();
        $idService = (int)$recuperateur->get('idService');

        if (! $idService) {
            $this->getConnecteurProperties()->setData('service_destinataire_libelle', '');
            $this->getConnecteurProperties()->setData('service_destinataire', '');
            return true;
        }

        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        $serviceInfo =  $cpp->getService($idService);

        $this->getConnecteurProperties()->setData(
            'service_destinataire_libelle',
            "{$serviceInfo['informationsGenerales']['nomService']} 
            ({$serviceInfo['informationsGenerales']['codeService']})"
        );
        $this->getConnecteurProperties()->setData('service_destinataire', $idService);

        return true;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function display()
    {
        $recuperateur = $this->getRecuperateur();
        $offset = (int)$recuperateur->get('offset', 0);
        $limit = CPPWrapper::NB_SERVICE_PAR_PAGE;

        $serviceList = $this->getListeService(intdiv($offset, $limit) + 1);
        $count = $serviceList['parametresRetour']['total'];

        $this->setViewParameter('service_list', $serviceList);
        $this->setViewParameter('offset', $offset);
        $this->setViewParameter('limit', $limit);
        $this->setViewParameter('count', $count);

        $this->renderPage("Choix d'un service Chorus Pro", 'connector/cpp/CPPChoixServiceTemplate');
        return true;
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function displayAPI()
    {
        $recuperateur = $this->getRecuperateur();
        $pageCourante = (int)$recuperateur->get('pageCourante');
        $nbResultatsParPage = (int)$recuperateur->get('nbResultatsParPage');
        return $this->getListeService($pageCourante, $nbResultatsParPage);
    }

    /**
     * @throws Exception
     */
    private function getListeService(
        int $pageCourante = 1,
        int $nbResultatsParPage = CPPWrapper::NB_SERVICE_PAR_PAGE
    ): array {
        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        return $cpp->getListeService($pageCourante, $nbResultatsParPage);
    }
}
