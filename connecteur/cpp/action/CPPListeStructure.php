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
        return json_encode(mb_convert_encoding($cpp->listeStructure(), 'UTF-8', 'ISO-8859-1'));
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
