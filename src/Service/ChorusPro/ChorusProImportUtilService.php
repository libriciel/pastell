<?php

namespace Pastell\Service\ChorusPro;

use ChorusProImportSQL;

class ChorusProImportUtilService
{
    public const string TYPE_SYNCHRONISATION_CREATION = 'C';
    public const string TYPE_SYNCHRONISATION_SYNCHRO = 'S';

    public const string TYPE_INTEGRATION_CPP_CLE = 'CPP';
    public const string TYPE_INTEGRATION_CPP_VALEUR = 'Importation Chorus Pro';

    public const string TYPE_INTEGRATION_CPP_TRAVAUX_CLE = 'CPP_TRAVAUX';
    public const string TYPE_INTEGRATION_CPP_TRAVAUX_VALEUR = 'Importation Chorus Pro Facture de Travaux (MOE/MOA)';

    public const string NOMMAGE_ID_FACTURE_CSV = '-99-csv';
    public const string TYPE_INTEGRATION_CSV_CLE = 'CSV';
    public const string TYPE_INTEGRATION_CSV_VALEUR = 'Importation Chorus Pro par CSV';

    public function __construct(
        private readonly ChorusProImportSQL $chorusProImportSQL,
    ) {
    }

    public function getOldestDateDepuisLe(string $id_e, string $dateDepuisLe, string $typeIntegration): string
    {
        // date_statut_courant la plus récente
        $mostRecentDateStatutCourant = $this->chorusProImportSQL->getMostRecentDateStatutCourant($id_e, $typeIntegration);

        if (! $mostRecentDateStatutCourant) {
            return $dateDepuisLe;
        }
        return min($dateDepuisLe, $mostRecentDateStatutCourant); // Date la plus ancienne
        // Exemples, avec 01/01/2021 et 01/01/2019 Alors mostRecentDateStatutCourant = 01/01/2021
        // et (depuis le 01/01/2020 => 01/01/2020), (depuis le 01/01/2022 => 01/01/2021)
    }

    public function getListeFacturePastell($id_e, string $typeIntegration, string $utilisateurTechnique = ''): array
    {
        return $this->chorusProImportSQL->getListeFacturePastell($id_e, $typeIntegration, $utilisateurTechnique);
    }

    /**
     * @param $id_facture_cpp
     * @param $liste_facture_pastell
     * @return mixed
     */
    public function rechercherDocumentPastell($id_facture_cpp, $liste_facture_pastell): mixed
    {
        foreach ($liste_facture_pastell as $facture_pastell) {
            if (strcmp($facture_pastell['id_facture_cpp'], $id_facture_cpp) === 0) {
                return $facture_pastell;
            }
        }
        // Document non trouvé.
        return false;
    }

    /**
     * @param $result
     * @return string
     */
    public function miseEnFormeResult($result): string
    {
        $message = '';
        $retour = [];

        foreach ($result as $line) {
            if (empty($retour[$line['message']])) {
                $retour[$line['message']]['nb'] = 0;
                $retour[$line['message']]['factures'] = '';
            }
            $retour[$line['message']]['nb'] += 1;
            $retour[$line['message']]['message'] = $line['message'];
            $retour[$line['message']]['factures'] .= $line['id_facture_cpp'] . ', ';
        }

        foreach ($retour as $values) {
            $message .= '"' . $values['message'] . '" pour ' . $values['nb'] . ' facture(s): ' . $values['factures'] . '<br/>';
        }
        return $message;
    }
}
