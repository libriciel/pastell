<?php

declare(strict_types=1);

namespace Pastell\Step\SAE\Action;

use ConnecteurTypeActionExecutor;
use DonneesFormulaireException;
use FluxData;
use FluxDataSedaDefault;
use NotFoundException;
use Pastell\Step\SAE\Enum\SAEActionsEnum;
use Pastell\Step\SAE\Enum\SAEFieldsEnum;
use SEDAConnecteur;
use TmpFolder;
use UnrecoverableException;

final class SAEGenerateArchiveAction extends ConnecteurTypeActionExecutor
{
    /**
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws \JsonException
     * @throws \Exception
     */
    public function go()
    {
        $generateSipErrorState = $this->getMappingValue(SAEActionsEnum::GENERATE_SIP_ERROR->value);
        $tmpFolder = new TmpFolder();
        $tmp_folder = $tmpFolder->create();
        $result = false;
        try {
            $result = $this->goInternal($tmp_folder);
        } catch (UnrecoverableException $e) {
            $this->changeOrUpdateAction($generateSipErrorState, $e->getMessage());
            $this->notify($generateSipErrorState, $this->type, $e->getMessage());
        } finally {
            $tmpFolder->delete($tmp_folder);
        }

        return $result;
    }

    /**
     * @throws UnrecoverableException
     * @throws NotFoundException
     * @throws DonneesFormulaireException
     * @throws \JsonException
     * @throws \Exception
     */
    public function goInternal(string $tempDir): bool
    {
        $sae_show = $this->getMappingValue(SAEFieldsEnum::SAE_SHOW->value);
        $sae_bordereau = $this->getMappingValue(SAEFieldsEnum::SAE_BORDEREAU->value);
        $sae_archive = $this->getMappingValue(SAEFieldsEnum::SAE_ARCHIVE->value);
        $sae_config = $this->getMappingValue(SAEFieldsEnum::SAE_CONFIG->value);
        $donneesFormulaire = $this->getDonneesFormulaire();
        $donneesFormulaire->setData($sae_show, true);
        $this->createJournal();

        /** @var SEDAConnecteur $seda */
        $seda = $this->getConnecteur('Bordereau SEDA');
        $fluxDataClassName = $this->getDataSedaClassName() ?? FluxDataSedaDefault::class;

        /** @var FluxData $fluxData */
        $fluxData = new $fluxDataClassName($donneesFormulaire);

        if (\method_exists($fluxData, 'setMetadata') && $donneesFormulaire->get($sae_config)) {
            try {
                $metadata = \json_decode(
                    $donneesFormulaire->getFileContent($sae_config),
                    true,
                    512,
                    \JSON_THROW_ON_ERROR
                ) ?: [];
            } catch (\JsonException $e) {
                throw new UnrecoverableException('Fichier de configuration SAE : ' . $e->getMessage());
            }
            $fluxData->setMetadata($metadata);
        }

        $bordereau = $seda->getBordereau($fluxData);
        $donneesFormulaire->addFileFromData($sae_bordereau, 'bordereau.xml', $bordereau);

        try {
            $seda->validateBordereau($bordereau);
        } catch (\Exception $e) {
            $message = $e->getMessage() . ' : <br/><br/>';
            foreach ($seda->getLastValidationError() as $erreur) {
                $message .= $erreur->message . '<br/>';
            }
            throw new UnrecoverableException($message);
        }

        $archive_path = $tempDir . '/archive.tar.gz';
        $seda->generateArchive($fluxData, $archive_path);

        $donneesFormulaire->addFileFromCopy($sae_archive, 'archive.tar.gz', $archive_path);
        $message = "L'archive a été générée";
        $this->addActionOK($message);
        $this->notify($this->action, $this->type, $message);
        return true;
    }

    /**
     * @throws NotFoundException
     * @throws \JsonException
     * @throws \Exception
     */
    private function createJournal(): void
    {
        $journal_mapping = $this->getMappingValue(SAEFieldsEnum::JOURNAL->value);
        $date_journal_debut_mapping = $this->getMappingValue(SAEFieldsEnum::JOURNAL_START_DATE->value);
        $date_cloture_journal_mapping = $this->getMappingValue(SAEFieldsEnum::JOURNAL_END_DATE->value);
        $date_cloture_journal_iso8601_mapping = $this->getMappingValue(SAEFieldsEnum::JOURNAL_END_DATE_ISO8601->value);

        $journal = $this->getJournal()->getAll($this->id_e, false, $this->id_d, 0, 0, 10000);
        foreach ($journal as $i => $journal_item) {
            $journal[$i]['preuve'] = \base64_encode($journal_item['preuve']);
        }

        $date_journal_debut = $journal[\count($journal) - 1]['date'];
        $date_cloture_journal = $journal[0]['date'];

        $journal = \json_encode($journal, \JSON_THROW_ON_ERROR);

        $this->getDonneesFormulaire()->addFileFromData($journal_mapping, 'journal.json', $journal);
        $this->getDonneesFormulaire()->setData(
            $date_journal_debut_mapping,
            \date('Y-m-d', \strtotime($date_journal_debut))
        );
        $this->getDonneesFormulaire()->setData(
            $date_cloture_journal_mapping,
            \date('Y-m-d', \strtotime($date_cloture_journal))
        );
        $this->getDonneesFormulaire()->setData(
            $date_cloture_journal_iso8601_mapping,
            \date('c', \strtotime($date_cloture_journal))
        );
    }
}
