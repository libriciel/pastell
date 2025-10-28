<?php

class TdtSendReponsePref extends ConnecteurTypeActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var TdtConnecteur $tdT */
        $tdT = $this->getConnecteur('TdT');
        $tdT->sendResponse($this->getDonneesFormulaire());
        $message = 'Réponse envoyée à la préfecture';
        $this->addActionOK($message);
        $this->setLastMessage($message);

        return true;
    }
}
