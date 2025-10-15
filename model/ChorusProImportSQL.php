<?php

declare(strict_types=1);

final class ChorusProImportSQL extends SQL
{
    public function getMostRecentDateStatutCourant($id_e, string $typeIntegration): mixed
    {
        $query = <<<SQL
SELECT di.field_value FROM document_index di
JOIN document_entite de ON de.id_d=di.id_d
JOIN document_index di_integration
    ON de.id_d=di_integration.id_d AND di_integration.field_name = 'type_integration' AND di_integration.field_value =?
WHERE de.id_e=? AND di.field_name='date_statut_courant'
ORDER BY di.field_value DESC LIMIT 1
SQL;
        return $this->queryOne($query, $typeIntegration, $id_e);
    }

    public function getListeFacturePastell($id_e, string $typeIntegration, string $utilisateurTechnique = ''): array
    {
        // Chargement des factures cpp présentes sur le Pastell
        $sql = <<<SQL
SELECT de.id_d, de.id_e, di_id_facture_cpp.field_value AS id_facture_cpp, di_statut_cpp.field_value AS statut_cpp
FROM document_entite de
INNER JOIN document_index di_id_facture_cpp
    ON di_id_facture_cpp.id_d = de.id_d AND di_id_facture_cpp.field_name = 'id_facture_cpp'
INNER JOIN document_index di_statut_cpp
    ON de.id_d = di_statut_cpp.id_d AND di_statut_cpp.field_name = 'statut_cpp'
INNER JOIN document_index di_type_integration
    ON de.id_d = di_type_integration.id_d
        AND di_type_integration.field_name = 'type_integration'
        AND di_type_integration.field_value =?
SQL;
        if ($utilisateurTechnique) {
            $sql .= <<<SQL
INNER JOIN document_index di_utilisateur_technique
    ON de.id_d = di_utilisateur_technique.id_d
        AND di_utilisateur_technique.field_name = 'utilisateur_technique'
        AND di_utilisateur_technique.field_value =?
SQL;
        }
        $sql .= <<<SQL
WHERE de.id_e=?
SQL;

        if ($utilisateurTechnique) {
            return $this->query($sql, $typeIntegration, $utilisateurTechnique, $id_e);
        }
        return $this->query($sql, $typeIntegration, $id_e);
    }
}
