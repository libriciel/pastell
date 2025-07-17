<?php

declare(strict_types=1);

class CPPListeFacture extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function metier(): string
    {
        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        return json_encode(
            mb_convert_encoding(
                $cpp->rechercheFactureParRecipiendaire('', $cpp->getDateDepuisLe(), $cpp->getDateJusquAu()),
                'UTF-8',
                'ISO-8859-1'
            )
        );
    }

    /**
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        $result = $this->metier();
        if (! $result) {
            $this->setLastMessage('La connexion cpp a échoué : ' . $cpp->getLastError());
            return false;
        }
        $this->setLastMessage(
            sprintf(
                'Liste des factures ayant changé de statut entre le %s et le %s : %s',
                $cpp->getDateDepuisLe(),
                $cpp->getDateJusquAu(),
                $result
            )
        );
        return true;
    }
}
