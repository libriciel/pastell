<?php

use Pastell\Service\Droit\DroitType;

class DocumentCount
{
    private $roleUtilisateur;
    private $documentEntite;
    private $entiteSQL;
    private $extensions;
    private $documentTypeFactory;

    public function __construct(
        RoleUtilisateur $roleUtilisateur,
        DocumentEntite $documentEntite,
        EntiteSQL $entiteSQL,
        Extensions $extensions,
        DocumentTypeFactory $documentTypeFactory
    ) {
        $this->roleUtilisateur = $roleUtilisateur;
        $this->documentEntite = $documentEntite;
        $this->entiteSQL = $entiteSQL;
        $this->extensions = $extensions;
        $this->documentTypeFactory = $documentTypeFactory;
    }


    public function getAll($id_u, $id_e = false, $type = false)
    {

        if ($type) {
            $all_type = [$type];
        } else {
            $all_type = array_keys($this->documentTypeFactory->clearRestrictedFlux($this->extensions->getAllModule()));
        }

        $all_count = $this->documentEntite->getCountAction($id_e, $type);


        foreach ($all_count as $info) {
            $count[$info['id_e']][$info['type']][$info['last_action']] = $info['count'];
        }

        $all_droit = $this->roleUtilisateur->getAllEntiteDroit($id_u, $id_e);

        $result = [];

        foreach ($all_droit as $info) {
            if (! preg_match('#(.*):' . DroitType::LECTURE->value . '#', $info['droit'], $matches)) {
                continue;
            }
            $type_match = $matches[1];
            if (! in_array($type_match, $all_type)) {
                continue;
            }
            $result[$info['id_e']]['flux'][$type_match] = [];
            if (isset($count[$info['id_e']][$type_match])) {
                $result[$info['id_e']]['flux'][$type_match] = $count[$info['id_e']][$type_match];
            }
        }

        foreach ($result as $id_e => $info) {
            $entity = $this->entiteSQL->getInfo($id_e);
            $entity['id_e'] = (string)$entity['id_e'];
            $entity['centre_de_gestion'] = (string)$entity['centre_de_gestion'];
            $entity['is_active'] = (bool)$entity['is_active'];
            $result[$id_e]['info'] = $entity;
        }

        return $result;
    }
}
