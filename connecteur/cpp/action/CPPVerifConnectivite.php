<?php

use Pastell\Mailer\AdminMailer;

class CPPVerifConnectivite extends ActionExecutor
{
    /**
     * @throws Exception
     */
    public function go()
    {

        $data = '';
        $nb_ko = 0;

        $result = $this->traiterCPP();
        if ($result) {
            $data .= "Connecteurs Chorus Pro:\n\n";
            $data .= "Nombre de connexions Chorus Pro ok : " . $result['nb_ok'] .
                "\nNombre de connexions Chorus Pro en erreur : " . $result['nb_ko'] . "\n\n" . $result['data'] . "\n\n";
            $nb_ko += $result['nb_ko'];
        }

        if (!$data) {
            $data = "Il n'y a pas de connecteur Chorus Pro\n";
        }

        if ($nb_ko) {
            $admin_email = $this->objectInstancier->getInstance(ConfigurationSQL::class)->getAdminEmails();
            $this->objectInstancier
                ->getInstance(AdminMailer::class)
                ->notifyText('[Pastell] la connectivité Pastell - Chorus Pro est en erreur', $data);

            $data .= "\n\n mail envoyé à " . \implode(',', $admin_email);
        }

        $this->setLastMessage(nl2br($data));
        return $nb_ko === 0;
    }

    /**
     * @return array
     */
    public function traiterCPP()
    {
        $result_cpp = [];
        $data_cpp = '';
        $result_cpp['data'] = '';
        $result_cpp['nb_ok'] = 0;
        $result_cpp['nb_ko'] = 0;

        $all_connecteur = $this->objectInstancier
            ->getInstance(ConnecteurEntiteSQL::class)
            ->getAllById('cpp');

        foreach ($all_connecteur as $connecteur) {
            $result = '';
            if ($connecteur['id_e'] == 0) {
                continue;
            }

            $path = $this->getSiteBase() . '/Connecteur/edition?id_ce=' . $connecteur['id_ce'];

            /** @var CPP $cpp */
            $cpp = $this->getConnecteurFactory()->getConnecteurById($connecteur['id_ce']);

            $message = '';
            try {
                $result = $cpp->testConnexion();
            } catch (Exception $ex) {
                $message  = substr($ex->getMessage(), 0, 200);
            }

            if ($result) {
                $result_cpp['nb_ok']++;
            } else {
                $result_cpp['nb_ko']++;
                $data_cpp .= $connecteur['denomination'] . " - " . $connecteur['libelle'] . " : " . $path . "\n";
                $data_cpp .= $message . "\n";
            }
        }

        if ($result_cpp['nb_ko']) {
            $result_cpp['data'] = "Connexions en erreur:\n";
            $result_cpp['data'] .= $data_cpp . "\n";
        }

        return $result_cpp;
    }
}
