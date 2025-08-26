<?php

declare(strict_types=1);

namespace Pastell\Step\Tdt\Acte\Action;

use ConnecteurTypeActionExecutor;
use DonneesFormulaireFactory;
use Exception;
use NotFoundException;
use TdtConnecteur;
use UnrecoverableException;

use function sprintf;

class TdtTeletransmettreAction extends ConnecteurTypeActionExecutor
{
    /**
     * @throws Exception
     */
    public function go(): void
    {

        $stringMapper = $this->getDocumentType()->getAction()->getConnecteurMapper($this->action);

        /** @var TdtConnecteur $tdt */
        $tdt = $this->getConnecteur('TdT');

        $nounce_param = $tdt->getNounce();

        $redirect_url = $tdt->getRedirectURLForTeletransimission();
        $tedetis_transaction_id = $this->getDonneesFormulaire()->get($stringMapper->get('tedetis_transaction_id'));

        $return_teletransmission_tdt = $stringMapper->get('return-teletransmission-tdt');

        $this->changeAction('teletransmission-tdt', 'La télétransmission a été ordonnée depuis Pastell');

        $url_retour = sprintf(
            '%s/Document/action?id_d=%s&id_e=%s&action=%s&error=%%%%ERROR%%%%&message=%%%%MESSAGE%%%%',
            $this->getSiteBase(),
            $this->id_d,
            $this->id_e,
            $return_teletransmission_tdt
        );

        $to = $redirect_url . "?id={$tedetis_transaction_id}";
        if ($nounce_param) {
            $to .= '&' . $nounce_param;
        }
        $to .= '&url_return=' . urlencode($url_retour);
        header_wrapper("Location: $to");
        exit_wrapper();
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function goLot(array $all_id_d)
    {
        $stringMapper = $this->getDocumentType()->getAction()->getConnecteurMapper($this->action);
        $return_teletransmission_tdt = $stringMapper->get('return-teletransmission-tdt');

        $lst_id_d = '';
        $lst_id_transaction = '';

        /** @var TdtConnecteur $tdt */
        $tdt = $this->getConnecteur('TdT');

        $nounce_param = $tdt->getNounce();

        $redirect_url = $tdt->getRedirectURLForTeletransimissionMulti();

        foreach ($all_id_d as $id_d) {
            $lst_id_d .= 'id_d[]=' . $id_d . '&';
            $tedetis_transaction_id = $this->objectInstancier
                ->getInstance(DonneesFormulaireFactory::class)
                ->get($id_d)
                ->get($stringMapper->get('tedetis_transaction_id'));
            $lst_id_transaction .= "id[]=$tedetis_transaction_id&";
            $this->changeAction($this->action, 'La télétransmission par lot a été ordonnée depuis Pastell');
        }

        $this->setJobManagerForLot($all_id_d);

        $url_retour = sprintf(
            '%s/Document/retourTeletransmission?%stype=%s&id_e=%s&id_u=%s&action=%s',
            $this->getSiteBase(),
            $lst_id_d,
            $this->type,
            $this->id_e,
            $this->id_u,
            $return_teletransmission_tdt
        );
        $to = $redirect_url . "?{$lst_id_transaction}";
        if ($nounce_param) {
            $to .= '&' . $nounce_param;
        }
        $to .= '&url_return=' . urlencode($url_retour);

        header("Location: $to");
        exit;
    }
}
