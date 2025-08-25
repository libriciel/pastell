<?php

declare(strict_types=1);

class CPPListeFactureTravaux extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function metier(): string
    {
        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        return json_encode(
            $cpp->rechercheFactureTravaux($cpp->getDateDepuisLe(), $cpp->getDateJusquAu()),
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        if (!$this->getConnecteurProperties()->get('user_role')) {
            $this->setLastMessage(
                "Il faut sélectionner le rôle de l'utilisateur pour la récupération des factures de travaux"
            );
            return false;
        }
        $result = $this->metier();
        if (! $result) {
            $this->setLastMessage('La connexion cpp a échoué : ' . $cpp->getLastError());
            return false;
        }
        $this->setLastMessage(
            sprintf(
                'Liste des factures de travaux ayant changé de statut entre le %s et le %s : %s',
                $cpp->getDateDepuisLe(),
                $cpp->getDateJusquAu(),
                $result
            )
        );
        return true;
    }
}
