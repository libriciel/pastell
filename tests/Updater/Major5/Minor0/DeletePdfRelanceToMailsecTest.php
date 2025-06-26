<?php

declare(strict_types=1);

namespace Pastell\Tests\Updater\Major5\Minor0;

use Exception;
use Pastell\Updater\Major5\Minor0\DeletePdfRelanceToMailsec;
use PastellTestCase;

class DeletePdfRelanceToMailsecTest extends PastellTestCase
{
    /**
     * @throws Exception
     */
    public function testDeletePdfRelanceToMailsec(): void
    {
        $mailsecId = $this->createConnecteurForTypeDossier('ls-document-pdf', 'mailsec');
        $pdfRelanceId = $this->createConnecteurForTypeDossier('ls-document-pdf', 'pdf-relance');
        $this->configureConnector($pdfRelanceId, ['nb_day_relance' => '5', 'nb_day_next_state' => '10']);

        $this->getObjectInstancier()->getInstance(DeletePdfRelanceToMailsec::class)->update();
        $mailsecConfig = $this->getConnecteurFactory()->getConnecteurConfig($mailsecId);
        static::assertSame('5', $mailsecConfig->get('nb_day_relance'));
        static::assertSame('10', $mailsecConfig->get('nb_day_next_state'));
    }
}
