<?php

declare(strict_types=1);

namespace Pastell\Step\Tdt\Acte\Action;

use ConnecteurTypeActionExecutor;
use Exception;
use JsonException;
use NotFoundException;
use Pastell\Step\Tdt\Acte\TypePJ\TypePJProvider;
use TdtConnecteur;
use UnrecoverableException;

/**
 *
 * @deprecated PA 3.0.0
 * Il faut utiliser la fonction de l'API externalData et ne pas modifier directement type_acte et type_pj
 *
 * PA 5.0.0 Finalement on conserve par soucis de non regression (pour glaneur et transformation) Cf issue 2283
 *
 */
class TypologieOnChangeAction extends ConnecteurTypeActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws JsonException
     * @throws UnrecoverableException
     * @throws Exception
     */
    public function go(): bool
    {
        $result = [];

        $type_acte_element = $this->getMappingValue('type_acte');
        $type_pj_element = $this->getMappingValue('type_pj');
        $type_piece_element = $this->getMappingValue('type_piece');
        $type_piece_fichier_element = $this->getMappingValue('type_piece_fichier');

        $info = $this->displayAPI();

        $type_acte = $this->getDonneesFormulaire()->get($type_acte_element);
        $type_pj = json_decode(
            $this->getDonneesFormulaire()->get($type_pj_element, '[]'),
            false,
            512,
            JSON_THROW_ON_ERROR
        ) ?: [];

        if ($type_acte) {
            if (isset($info['actes_type_pj_list']) && ! \array_key_exists($type_acte, $info['actes_type_pj_list'])) {
                throw new UnrecoverableException(
                    \sprintf(
                        'Le type de pièce «%s» ne correspond pas pour la nature et la classification selectionnée',
                        $type_acte
                    )
                );
            }
            $result[] = [
                'filename' => $info['pieces'][0],
                'typologie' => $info['actes_type_pj_list'][$type_acte] ?? $type_acte,
            ];
        }

        if ($type_pj) {
            if ((count($type_pj)) !== (count($info['pieces']) - 1)) {
                throw new UnrecoverableException(
                    \sprintf(
                        "Le nombre de type de pièce «%s» ne correspond pas au nombre d'annexe «%d»",
                        count($type_pj),
                        count($info['pieces']) - 1
                    )
                );
            }
            foreach ($type_pj as $i => $type) {
                if (isset($info['actes_type_pj_list']) && ! array_key_exists($type, $info['actes_type_pj_list'])) {
                    throw new UnrecoverableException(
                        \sprintf(
                            'Le type de pièce «%s» ne correspond pas pour la nature et la classification selectionnée',
                            $type
                        )
                    );
                }
                $result[] = [
                    'filename' => $info['pieces'][$i + 1],
                    'typologie' => $info['actes_type_pj_list'][$type] ?? $type,
                ];
            }
        }

        $this->getDonneesFormulaire()->setData(
            $type_piece_element,
            (count($type_pj) + 1) . ' fichier(s) typé(s)'
        );

        $this->getDonneesFormulaire()->addFileFromData(
            $type_piece_fichier_element,
            'type_piece.json',
            json_encode($result, JSON_THROW_ON_ERROR)
        );

        return true;
    }

    /**
     * @throws Exception
     * @throws UnrecoverableException
     */
    public function displayAPI(): array
    {
        $result = [];

        $id_ce = $this->getConnecteurFactory()->getConnecteurId(
            $this->id_e,
            $this->type,
            TdtConnecteur::FAMILLE_CONNECTEUR
        );
        if (! $id_ce) {
            $result['pieces'] = $this->getAllPieces();
            return $result;
        }


        $configTdt = $this->getConnecteurConfigByType(TdtConnecteur::FAMILLE_CONNECTEUR);

        $result['actes_type_pj_list'] = $this->objectInstancier->getInstance(TypePJProvider::class)
            ->getTypePJListeForClassification(
                $configTdt->getFilePath($this->getMappingValue('classification_file')),
                $this->getDonneesFormulaire()->get($this->getMappingValue('acte_nature'))
            );
        if (! $result['actes_type_pj_list']) {
            throw new UnrecoverableException(
                'Aucun type de pièce ne correspond pour la nature et la classification selectionnée'
            );
        }

        $result['pieces'] = $this->getAllPieces();
        return $result;
    }

    /**
     * @return array|string
     * @throws UnrecoverableException|NotFoundException
     */
    private function getAllPieces(): array|string
    {

        $arrete_element = $this->getMappingValue('arrete');
        $autre_document_attache = $this->getMappingValue('autre_document_attache');

        $pieces_list = $this->getDonneesFormulaire()->get($arrete_element);
        if (! $pieces_list) {
            throw new UnrecoverableException("La pièce principale n'est pas présente");
        }
        if ($this->getDonneesFormulaire()->get($autre_document_attache)) {
            $pieces_list = array_merge($pieces_list, $this->getDonneesFormulaire()->get($autre_document_attache));
        }
        return $pieces_list;
    }

    public function updateJobQueueAfterExecution(): bool
    {
        return false;
    }
}
