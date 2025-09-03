<?php

declare(strict_types=1);

namespace Pastell\Step\Tdt\Acte\ChoiceAction;

use ConnecteurTypeChoiceActionExecutor;
use Exception;
use NotFoundException;
use Pastell\Step\Tdt\Acte\lib\ActesTypePJ;
use Pastell\Step\Tdt\Acte\lib\ActesTypePJData;
use TdtConnecteur;
use UnrecoverableException;

class TypologieChoiceAction extends ConnecteurTypeChoiceActionExecutor
{
    /**
     * arrete: arrete
     * autre_document_attache: autre_document_attache
     * type_acte: type_acte
     * type_pj: type_pj
     * type_piece: type_piece
     *
     * acte_nature: acte_nature
     * classification_file: classification_file
     *
     */


    /**
     * @throws Exception
     */
    public function display(): void
    {
        $connecteur_type_action = $this->getMappingList();

        $document_info = $this->getDocument()->getInfo($this->id_d);
        $this->setViewParameter('info', $document_info);

        $result = $this->displayAPI();
        if (empty($result['actes_type_pj_list'])) {
            throw new UnrecoverableException("La typologie des pièces jointes n'est pas disponible");
        }
        $this->setViewParameter('actes_type_pj_list', $result['actes_type_pj_list']);
        $this->setViewParameter('pieces', $result['pieces']);

        $type_pj_selection = [$this->getDonneesFormulaire()->get($connecteur_type_action['type_acte'] ?? 'type_acte')];

        $type_pj = $this->getDonneesFormulaire()->get($connecteur_type_action['type_pj'] ?? 'type_pj');
        if ($type_pj) {
            $type_pj_selection = array_merge($type_pj_selection, json_decode($type_pj));
        }
        $type_pj_selection = array_pad($type_pj_selection, count($this->getViewParameter()['pieces']), 0);

        $this->setViewParameter('type_pj_selection', $type_pj_selection);

        $this->renderPage('Choix des types de pièces', 'connectorType/tdt/TdtChoiceTypologieActesTemplate');
    }

    /**
     * @throws Exception
     * @throws UnrecoverableException
     */
    public function displayAPI(): array
    {
        $result = [];

        $connecteur_type_action = $this->getMappingList();

        $actesTypePJData = new ActesTypePJData();
        $id_ce = $this->getConnecteurFactory()->getConnecteurId(
            $this->id_e,
            $this->type,
            TdtConnecteur::FAMILLE_CONNECTEUR
        );
        if (!$id_ce) {
            $result['pieces'] = $this->getAllPieces();
            return $result;
        }

        $configTdt = $this->getConnecteurConfigByType(TdtConnecteur::FAMILLE_CONNECTEUR);
        $actesTypePJData->classificationFilePath =
            $configTdt->getFilePath($connecteur_type_action['classification_file'] ?? 'classification_file');

        if (!file_exists($actesTypePJData->classificationFilePath)) {
            throw new UnrecoverableException("Aucun fichier de classification n'est présent sur le connecteur TDT");
        }

        $actesTypePJData->acteNature =
            $this->getDonneesFormulaire()->get($connecteur_type_action['acte_nature'] ?? 'acte_nature');

        $actesTypePJ = $this->objectInstancier->getInstance(ActesTypePJ::class);

        $result['actes_type_pj_list'] = $actesTypePJ->getTypePJListe($actesTypePJData);
        if (!$result['actes_type_pj_list']) {
            throw new UnrecoverableException(
                'Aucun type de pièce ne correspond pour la nature et la classification sélectionnée'
            );
        }

        $result['pieces'] = $this->getAllPieces();
        return $result;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    private function getAllPieces(): array|string
    {
        $connecteur_type_action = $this->getMappingList();

        $pieces_list = $this->getDonneesFormulaire()->get($connecteur_type_action['arrete'] ?? 'arrete');
        if (!$pieces_list) {
            throw new UnrecoverableException("La pièce principale n'est pas présente");
        }
        $piecesAnnexe = $this->getDonneesFormulaire()
            ->get($connecteur_type_action['autre_document_attache'] ?? 'autre_document_attache');
        if ($piecesAnnexe) {
            $pieces_list = array_merge($pieces_list, $piecesAnnexe);
        }
        return $pieces_list;
    }

    private function getMappingList(): array|bool
    {
        return $this->getDocumentType()->getAction()->getProperties($this->action, 'connecteur-type-mapping');
    }

    /**
     * @throws Exception
     */
    public function go(): bool
    {

        $result = [];

        $connecteur_type_action = $this->getMappingList();

        $type_pj = $this->getRecuperateur()->get('type_pj');

        if ((empty($type_pj)) || (!is_array($type_pj))) {
            throw new UnrecoverableException('Aucun tableau type_pj fourni');
        }

        $info = $this->displayAPI();

        if ((count($type_pj)) !== (count($info['pieces']))) {
            throw new UnrecoverableException(
                sprintf(
                    'Le nombre de type_pj fourni «%d» ne correspond pas au nombre de documents (acte et annexes) «%d»',
                    count($type_pj),
                    count($info['pieces']),
                )
            );
        }
        foreach ($type_pj as $i => $type) {
            if (isset($info['actes_type_pj_list']) && !array_key_exists($type, $info['actes_type_pj_list'])) {
                throw new UnrecoverableException(
                    sprintf(
                        'Le type_pj «%s» ne correspond pas pour la nature et la classification sélectionnée',
                        $type,
                    )
                );
            }
            $result[] = ['filename' => $info['pieces'][$i], 'typologie' => $info['actes_type_pj_list'][$type] ?? $type];
        }

        $this->getDonneesFormulaire()->setData(
            $connecteur_type_action['type_piece'] ?? 'type_piece',
            count($type_pj) . ' fichier(s) typé(s)'
        );

        $type_acte = array_shift($type_pj);
        $this->getDonneesFormulaire()->setData($connecteur_type_action['type_acte'] ?? 'type_acte', $type_acte);
        $this->getDonneesFormulaire()->setData($connecteur_type_action['type_pj'] ?? 'type_pj', json_encode($type_pj));

        $this->getDonneesFormulaire()->addFileFromData(
            $connecteur_type_action['type_piece_fichier'] ?? 'type_piece_fichier',
            'type_piece.json',
            json_encode($result)
        );

        return true;
    }
}
