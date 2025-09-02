<?php

declare(strict_types=1);

use Symfony\Component\Serializer\Exception\ExceptionInterface;

class RecupFinParapheurRetrieveFolders extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): bool
    {
        /** @var RecupFinParapheur $recupParapheur */
        $recupParapheur = $this->getMyConnecteur();
        $id_d = $recupParapheur->recupOne();
        if ($id_d) {
            $message = 'Création des documents : ';
            foreach ($id_d as $id) {
                $message .= "\n- " . $id;
            }
        } else {
            $message = 'Aucun document à traiter.';
        }
        $this->setLastMessage($message);
        return true;
    }
}
