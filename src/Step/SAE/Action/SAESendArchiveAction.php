<?php

declare(strict_types=1);

namespace Pastell\Step\SAE\Action;

use ConnecteurTypeActionExecutor;
use NotFoundException;
use Pastell\Step\SAE\Enum\SAEActionsEnum;
use Pastell\Step\SAE\Enum\SAEFieldsEnum;
use SAEConnecteur;
use UnrecoverableException;

final class SAESendArchiveAction extends ConnecteurTypeActionExecutor
{
    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     */
    public function go()
    {
        $bordereau = $this->getDonneesFormulaire()->getFileContent(
            $this->getMappingValue(SAEFieldsEnum::SAE_BORDEREAU->value)
        );
        $archivePath = $this->getDonneesFormulaire()->getFilePath(
            $this->getMappingValue(SAEFieldsEnum::SAE_ARCHIVE->value)
        );
        $saeTransfertId = $this->getMappingValue(SAEFieldsEnum::SAE_TRANSFERT_ID->value);
        $sendArchiveErrorState = $this->getMappingValue(SAEActionsEnum::SEND_ARCHIVE_ERROR->value);

        /** @var SAEConnecteur $sae */
        $sae = $this->getConnecteur('SAE');

        try {
            $transfertId = $sae->sendSIP($bordereau, $archivePath);
        } catch (\Exception $exception) {
            $message = $exception->getMessage() . " - L'envoi du bordereau a échoué : " . $sae->getLastError();
            $this->changeOrUpdateAction($sendArchiveErrorState, $message);
            $this->notify($sendArchiveErrorState, $this->type, $message);
            return false;
        }

        $this->getDonneesFormulaire()->setData($saeTransfertId, $transfertId);

        $this->addActionOK('Le document a été envoyé au SAE');
        $this->notify($this->action, $this->type, 'Le document a été envoyé au SAE');
        return true;
    }
}
