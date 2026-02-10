<?php

declare(strict_types=1);

namespace Pastell\Updater\Major5\Minor0;

use ConnecteurEntiteSQL;
use ConnecteurFactory;
use ConnecteurFrequenceSQL;
use Exception;
use FluxEntiteSQL;
use Pastell\Service\Connecteur\ConnecteurDeletionService;
use Pastell\Updater\Version;
use PastellLogger;

final class DeletePdfRelanceToMailsec implements Version
{
    private const string PDF_RELANCE_CONNECTOR = 'pdf-relance';
    private const string MAILSEC_CONNECTOR = 'mailsec';
    private const string FEILD_NB_DAY_RELANCE = 'nb_day_relance';
    private const string FEILD_NB_DAY_NEXT_STATES = 'nb_day_next_state';

    public function __construct(
        private readonly ConnecteurEntiteSQL $connecteurEntiteSql,
        private readonly ConnecteurFactory $connecteurFactory,
        private readonly FluxEntiteSQL $fluxEntiteSQL,
        private readonly ConnecteurDeletionService $connecteurDeletionService,
        private readonly ConnecteurFrequenceSQL $connecteurFrequenceSQL,
        private readonly ?PastellLogger $logger = null,
    ) {
    }

    /**
     * @throws Exception
     */
    public function update(): void
    {
        $this->logger?->info('Start');

        $pdfRelanceConnectors = array_merge(
            $this->connecteurEntiteSql->getAllByConnecteurId(self::PDF_RELANCE_CONNECTOR),
            $this->connecteurEntiteSql->getAllByConnecteurId(self::PDF_RELANCE_CONNECTOR, true)
        );
        foreach ($pdfRelanceConnectors as $pdfRelanceConnector) {
            $pdfRelanceId = $pdfRelanceConnector['id_ce'];
            $pdfRelanceForm = $this->connecteurFactory->getConnecteurConfig($pdfRelanceId);

            $usageFluxList = $this->fluxEntiteSQL->getFluxAndEntityByConnectorId((string)$pdfRelanceId);
            foreach ($usageFluxList as $usage) {
                $mailsecId = $this->connecteurFactory->getConnecteurId(
                    $usage['id_e'],
                    $usage['flux'],
                    self::MAILSEC_CONNECTOR
                );
                if (!$mailsecId) {
                    $this->logger?->info(
                        \sprintf(
                            'Usage id_e `%s` flux `%s` : There is no %s connector',
                            $usage['id_e'],
                            $usage['flux'],
                            self::MAILSEC_CONNECTOR
                        )
                    );
                    break;
                }
                $mailsecForm = $this->connecteurFactory->getConnecteurConfig($mailsecId);
                $mailsecForm->setData(
                    self::FEILD_NB_DAY_RELANCE,
                    $pdfRelanceForm->get(self::FEILD_NB_DAY_RELANCE)
                );
                $mailsecForm->setData(
                    self::FEILD_NB_DAY_NEXT_STATES,
                    $pdfRelanceForm->get(self::FEILD_NB_DAY_NEXT_STATES)
                );
                $this->logger?->info(
                    \sprintf(
                        "Usage id_e `%s` flux `%s` : Update connector %s id_ce = '%s' with values connector %s id_ce = '%s'",
                        $usage['id_e'],
                        $usage['flux'],
                        self::MAILSEC_CONNECTOR,
                        $mailsecId,
                        self::PDF_RELANCE_CONNECTOR,
                        $pdfRelanceId,
                    )
                );
            }
            $this->connecteurDeletionService->disassociate($pdfRelanceId);
            $this->connecteurDeletionService->deleteConnecteur($pdfRelanceId);
            $this->logger?->info(
                \sprintf(
                    "Delete connector %s id_ce = '%s'",
                    self::PDF_RELANCE_CONNECTOR,
                    $pdfRelanceId,
                )
            );
        }
        //MAJ Frequence
        $allFrequencies = $this->connecteurFrequenceSQL->getAll();
        foreach ($allFrequencies as $frequency) {
            if ($frequency->famille_connecteur === self::PDF_RELANCE_CONNECTOR) {
                $frequency->famille_connecteur = self::MAILSEC_CONNECTOR;
                $frequency->id_connecteur = self::MAILSEC_CONNECTOR;
                $this->connecteurFrequenceSQL->edit($frequency);
                $this->logger?->info('Update frequence mailsec with values pdf-relance');
            }
        }
    }
}
