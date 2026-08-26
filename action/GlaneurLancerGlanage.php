<?php

use Pastell\Exception\NotificationException;

class GlaneurLancerGlanage extends ActionExecutor
{
    /**
     * @return bool
     * @throws Exception
     */
    public function go()
    {
        /** @var GlaneurConnecteur $connecteur */
        $connecteur = $this->getMyConnecteur();

        try {
            $result = $connecteur->glaner();
            $this->setLastMessage(implode('<br/>', $connecteur->getLastMessage()));
        } catch (UnrecoverableException $e) {
            $message = $e->getMessage();

            $url = sprintf('%s/Connecteur/edition?id_ce=%d', $this->getSiteBase(), $this->id_ce);

            throw new NotificationException(
                $message,
                "[Pastell] Le traitement d'un glaneur est en erreur et suspendu",
                'glaneur_lancer_glanage.html.twig',
                ['url' => $url, 'message' => $message],
                $e,
            );
        } catch (Exception $e) {
            $this->setLastMessage("Erreur lors de l'importation : " . $e->getMessage() . "<br />\n");
            return false;
        }

        return $result;
    }
}
