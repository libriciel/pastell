<?php

declare(strict_types=1);

class MailSecControlerTest extends ControlerTestCase
{
    public function testAnnuaire(): void
    {
        /** @var MailSecControler $mailSecControler */
        $mailSecControler = $this->getControlerInstance(MailSecControler::class);
        \ob_start();
        $mailSecControler->annuaireAction();
        \ob_end_clean();
        $view_parameter = $mailSecControler->getViewParameter();
        $this->assertEquals(0, $view_parameter['id_e']);
    }

    /**
     * @throws NotFoundException
     */
    public function testAnnuaireImport(): void
    {
        /** @var MailSecControler $mailsecController */
        $mailsecController = $this->getControlerInstance(MailSecControler::class);
        \ob_start();
        $mailsecController->contactImportAction();
        \ob_end_clean();
        $view_parameter = $mailsecController->getViewParameter();

        $this->assertSame(0, $view_parameter['id_e']);
        $this->assertSame('Annuaire global', $view_parameter['infoEntite']['denomination']);
    }
}
