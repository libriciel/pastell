<?php

use Pastell\Mailer\Mailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

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

        $result = $this->traiterChorusCSV();
        if ($result) {
            $data .= "Connecteurs Chorus Pro par CSV:\n\n";
            $data .= $result['data'] . "\n\n";
            $nb_ko += $result['nb_ko'];
        }

        if (!$data) {
            $data = "Il n'y a pas de connecteur Chorus Pro\n";
        }

        if ($nb_ko) {
            $configurationSql = $this->objectInstancier->getInstance(ConfigurationSQL::class);
            $admin_email = $configurationSql->getAdminEmails();
            $libelle_plateforme_mail = $configurationSql->getLibellePlateformeMail();
            $templatedEmail = new TemplatedEmail()
                ->to(...$admin_email)
                ->subject("[$libelle_plateforme_mail] la connectivité Pastell - Chorus Pro est en erreur")
                ->text($data);
            $this->objectInstancier
                ->getInstance(Mailer::class)
                ->send($templatedEmail);

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

    /**
     * @return array
     */
    public function traiterChorusCSV()
    {
        $result_cpp = [];
        $result_cpp['data'] = '';
        $result_cpp['nb_ok'] = 0;
        $result_cpp['nb_ko'] = 0;

        $all_connecteur = $this->objectInstancier
            ->getInstance(ConnecteurEntiteSQL::class)
            ->getAllById('chorus-par-csv');

        foreach ($all_connecteur as $connecteur) {
            $result_connecteur_data = '';
            $result_connecteur_ok = 0;
            $result_connecteur_ko = 0;
            $message_erreur = '';

            $list_login_pass = [];

            if ($connecteur['id_e'] == 0) {
                continue;
            }

            $path = $this->getSiteBase() . '/Connecteur/edition?id_ce=' . $connecteur['id_ce'];
            $result_cpp['data'] .= $connecteur['denomination'] . " - " . $connecteur['libelle'] . " : " . $path . "\n";

            /** @var DonneesFormulaire $chorusParCsvConfig */
            $chorusParCsvConfig = $this->getConnecteurFactory()->getConnecteurConfig($connecteur['id_ce']);

            $fichier_csv = $chorusParCsvConfig->getFilePath('fichier_csv_interprete');
            $CSV = new CSV();
            $colList = $CSV->get($fichier_csv, ';');

            if (!$colList) {
                $message_erreur = "Il n'y a pas de fichier CSV interprété";
            }

            foreach ($colList as $col) {
                if (count($col) == 6) { // Ex: DEV_DESTTAA074@cpp2017.fr;"Riuxdnup64167[";00000000013456;25784152;00000000013357;25784150
                    if (!array_key_exists($col[0], $list_login_pass)) {
                        $list_login_pass[$col[0]] = $col[1];
                    }
                } else {
                    $message_erreur .= "Le fichier CSV interprété n'est pas correct";
                }
            }

            foreach ($list_login_pass as $login => $password) {
                $result = '';
                $message = '';
                $chorusParCsvConfig->setData('user_login', $login);
                $chorusParCsvConfig->setData('user_password', $password);

                /** @var ChorusParCsv $chorusParCsv */
                $chorusParCsv = $this->getConnecteurFactory()->getConnecteurById($connecteur['id_ce']);
                try {
                    $result = $chorusParCsv->testConnexion();
                } catch (Exception $ex) {
                    $message  = substr($ex->getMessage(), 0, 200);
                }

                if ($result) {
                    $result_connecteur_ok++;
                } else {
                    $result_connecteur_ko++;
                    $result_connecteur_data .= $message . "\n";
                }
            }

            if ($message_erreur) {
                $result_cpp['nb_ko']++;
                $result_cpp['data'] .= 'Le connecteur est en erreur: ' . $message_erreur . "\n\n";
            } else {
                $result_cpp['data'] .= "Nombre de connexions Chorus Pro par CSV ok: " . $result_connecteur_ok .
                    "\nNombre de connexions Chorus Pro par CSV en erreur : " . $result_connecteur_ko . "\n\n";
                if ($result_connecteur_ko) {
                    $result_cpp['nb_ko'] = $result_cpp['nb_ko'] + $result_connecteur_ko;
                    $result_cpp['data'] .= "Connexions en erreur:\n";
                    $result_cpp['data'] .= $result_connecteur_data . "\n\n";
                }
            }
        }

        return $result_cpp;
    }
}
