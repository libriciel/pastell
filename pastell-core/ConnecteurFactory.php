<?php

use Monolog\Logger;
use Pastell\Service\Entite\RootEntity;

class ConnecteurFactory
{
    public function __construct(private readonly ObjectInstancier $objectInstancier)
    {
    }

    /**
     *
     * @param int $id_ce
     * @return Connecteur
     * @throws Exception
     */
    public function getConnecteurById($id_ce)
    {
        $connecteur_info = $this->objectInstancier->getInstance(ConnecteurEntiteSQL::class)->getInfo($id_ce);
        return $this->getConnecteurObjet($connecteur_info);
    }

    /**
     * @param $id_ce
     * @return DonneesFormulaire
     * @throws Exception
     */
    public function getConnecteurConfig($id_ce)
    {
        return $this->objectInstancier->getInstance(DonneesFormulaireFactory::class)->getConnecteurEntiteFormulaire($id_ce);
    }

    public function getConnecteurId($id_e, $id_flux, $type_connecteur, $num_same_type = 0)
    {
        return $this->objectInstancier
            ->getInstance(FluxEntiteHeritageSQL::class)
            ->getConnecteurId($id_e, $id_flux, $type_connecteur, $num_same_type);
    }

    public function getConnecteurByType($id_e, $id_flux, $type_connecteur, $num_same_type = 0)
    {
        $id_ce = $this->getConnecteurId($id_e, $id_flux, $type_connecteur, $num_same_type);
        if (! $id_ce) {
            return false;
        }
        return $this->getConnecteurById($id_ce);
    }

    public function getConnecteurConfigByType($id_e, $id_flux, $type_connecteur, $num_same_type = 0): DonneesFormulaire|false
    {
        $id_ce = $this->getConnecteurId($id_e, $id_flux, $type_connecteur, $num_same_type);
        if (! $id_ce) {
            return false;
        }
        return $this->getConnecteurConfig($id_ce);
    }

    /**
     * @param array $connecteur_info
     * @return Connecteur|false
     * @throws Exception
     */
    private function getConnecteurObjet($connecteur_info): Connecteur|false
    {
        if (!$connecteur_info) {
            return false;
        }
        $this->controleRestriction($connecteur_info);
        $class_name = $this->objectInstancier
            ->getInstance(ConnecteurDefinitionFiles::class)
            ->getConnecteurClass($connecteur_info['id_connecteur']);
        /** @var Connecteur $connecteurObject */
        $connecteurObject = $this->objectInstancier->newInstance($class_name);
        $connecteurObject->setConnecteurInfo($connecteur_info);
        $connecteurObject->setLogger($this->objectInstancier->getInstance(Logger::class));
        $connecteurObject->setConnecteurConfig($this->getConnecteurConfig($connecteur_info['id_ce']));
        $connecteurObject->setDataDir($this->objectInstancier->getInstance('data_dir'));
        return $connecteurObject;
    }

    public function getGlobalConnecteur($type): Connecteur|false
    {
        return $this->getConnecteurByType(0, 'global', $type);
    }

    public function getGlobalConnecteurConfig($type): DonneesFormulaire|false
    {
        return $this->getConnecteurConfigByType(0, 'global', $type);
    }

    /**
     * @return array
     */
    public function getManquant(): array
    {
        $connecteur_manquant_list = [];
        $all_connecteur = $this->objectInstancier
            ->getInstance(Extensions::class)
            ->getAllConnecteur();

        $all_connecteur_global_used = $this->objectInstancier
            ->getInstance(ConnecteurEntiteSQL::class)
            ->getAllUsedByScope(true);
        foreach ($all_connecteur_global_used as $id_connecteur) {
            if (
                !in_array($id_connecteur, $connecteur_manquant_list, true)
                && (
                    empty($all_connecteur[$id_connecteur])
                    || $this->isRestrictedConnecteur($id_connecteur, true)
                )
            ) {
                $connecteur_manquant_list[] = $id_connecteur;
            }
        }

        $all_connecteur_entite_used = $this->objectInstancier
            ->getInstance(ConnecteurEntiteSQL::class)
            ->getAllUsedByScope(false);
        foreach ($all_connecteur_entite_used as $id_connecteur) {
            if (
                !in_array($id_connecteur, $connecteur_manquant_list, true)
                && (
                    empty($all_connecteur[$id_connecteur])
                    || $this->isRestrictedConnecteur($id_connecteur)
                )
            ) {
                $connecteur_manquant_list[] = $id_connecteur;
            }
        }

        asort($connecteur_manquant_list);
        return $connecteur_manquant_list;
    }

    /**
     * @param array $connecteur_info
     * @return bool
     * @throws Exception
     */
    public function controleRestriction(array $connecteur_info): bool
    {
        if ($connecteur_info['id_e'] == RootEntity::ID_E) {
            if ($this->isRestrictedConnecteur($connecteur_info['id_connecteur'], true)) {
                throw new Exception("Action impossible: Le connecteur " . $connecteur_info['id_connecteur'] . " est restreint sur cette plateforme.");
            }
        } elseif ($this->isRestrictedConnecteur($connecteur_info['id_connecteur'])) {
            throw new Exception("Action impossible: Le connecteur " . $connecteur_info['id_connecteur'] . " est restreint sur cette plateforme.");
        }
        return true;
    }


    /**
     * @param string $id_connecteur
     * @param bool $global
     * @return bool
     */
    public function isRestrictedConnecteur(string $id_connecteur, bool $global = false): bool
    {
        return in_array(
            $id_connecteur,
            $this->objectInstancier->getInstance(ConnecteurDefinitionFiles::class)->getAllRestricted($global),
            true
        );
    }
}
