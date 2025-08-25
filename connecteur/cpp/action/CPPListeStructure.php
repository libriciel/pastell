<?php

declare(strict_types=1);

class CPPListeStructure extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function metier(): string
    {
        /** @var CPP $cpp */
        $cpp = $this->getMyConnecteur();
        return json_encode($cpp->listeStructure(), JSON_THROW_ON_ERROR);
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
        $this->setLastMessage('Liste des structures : ' . $result);
        return true;
    }
}
