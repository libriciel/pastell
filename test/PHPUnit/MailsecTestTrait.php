<?php

trait MailsecTestTrait
{
    public function createMailSec(string $flux_name, string $action_envoi): array
    {
        $this->createConnecteurForTypeDossier($flux_name, 'mailsec');

        $id_d = $this->createDocument($flux_name)['id_d'];
        $this->configureDocument($id_d, [
            'objet' => 'test de mail',
            'to' => 'test@libriciel.fr',
            'message' => 'message de test'
        ]);
        $this->triggerActionOnDocument($id_d, $action_envoi);

        $info = $this->getObjectInstancier()->getInstance(DocumentEmailSQL::class)->getAllByDocument($id_d);
        $key = $info[0]['key'];

        return ['id_d' => $id_d,'key' => $key];
    }
}
