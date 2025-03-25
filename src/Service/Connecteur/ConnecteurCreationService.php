<?php

namespace Pastell\Service\Connecteur;

use ConnecteurEntiteSQL;
use ConnecteurException;
use ConnecteurFactory;
use DonneesFormulaireFactory;
use Exception;

class ConnecteurCreationService
{
    public function __construct(
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly ConnecteurEntiteSQL $connecteurEntiteSQL,
        private readonly ConnecteurActionService $connecteurActionService,
        private readonly ConnecteurAssociationService $connecteurAssociationService,
        private readonly DonneesFormulaireFactory $donneesFormulaireFactory,
    ) {
    }

    /**
     * @param int<0,1> $global
     * @throws Exception
     * @throws ConnecteurException
     */
    public function createConnecteur(
        string $connecteur_id,
        string $type,
        int $global = 0,
        int $id_e = 0,
        int $id_u = 0,
        string $libelle = '',
        array $data = [],
        string $message = ''
    ): int {

        $this->checkCreateConnector($id_e, $global);
        $libelle = ($libelle === '') ? $connecteur_id : $libelle;

        $id_ce =  $this->connecteurEntiteSQL->addConnecteur(
            $id_e,
            $connecteur_id,
            $type,
            $libelle,
            $global
        );

        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);
        $donneesFormulaire->setTabData($data);
        $this->setDefaultValue($id_ce);

        $this->connecteurActionService->add(
            $id_e,
            $id_u,
            $id_ce,
            '',
            ConnecteurActionService::ACTION_AJOUTE,
            $message
        );

        return $id_ce;
    }
    /**
     * @param $type
     * @return bool
     */
    public function hasConnecteurGlobal($type): bool
    {
        $connecteurGlobal = $this->connecteurFactory->getGlobalConnecteur($type);
        return (bool) $connecteurGlobal;
    }

    /**
     * @param string $connecteur_id
     * @param string $type
     * @param string $libelle
     * @param array $data
     * @return int
     * @throws Exception
     */
    public function createAndAssociateGlobalConnecteur(
        string $connecteur_id,
        string $type,
        string $libelle = '',
        array $data = []
    ): int {
        $id_ce = $this->createConnecteur(
            $connecteur_id,
            $type,
            1,
            0,
            0,
            $libelle,
            $data,
            "Le connecteur $connecteur_id « $libelle » a été créé par « Pastell »"
        );

        $this->connecteurAssociationService->addConnecteurAssociation(
            0,
            $id_ce,
            $type
        );

        return $id_ce;
    }

    /**
     * @throws Exception
     */
    private function setDefaultValue(int $id_ce): void
    {
        $donneesFormulaire = $this->donneesFormulaireFactory->getConnecteurEntiteFormulaire($id_ce);
        foreach ($donneesFormulaire->getFormulaire()->getAllFields() as $field) {
            if ($field->getDefault()) {
                $donneesFormulaire->setData($field->getName(), $field->getDefault());
            }
        }
    }

    /**
     * @throws ConnecteurException
     */
    private function checkCreateConnector(int $id_e, int $global): void
    {
        if (($id_e !== 0) && ($global === 1)) {
            throw new ConnecteurException(
                "Il n'est pas possible de créer un connecteur global au niveau d'une entité"
            );
        }
    }
}
