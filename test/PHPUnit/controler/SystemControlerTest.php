<?php

use Pastell\Bootstrap\SystemConfiguration;
use Pastell\Mailer\Mailer;
use Pastell\Tests\MailerTransportTesting;

class SystemControlerTest extends ControlerTestCase
{
    /** @var  SystemControler */
    private $systemControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemControler = $this->getControlerInstance("SystemControler");
        $this->getObjectInstancier()->getInstance(ConfigurationSQL::class)
            ->setAdminEmails(['test@libriciel.invalid']);
    }

    /**
     * @throws NotFoundException
     */
    public function testFluxDetailAction()
    {
        $this->expectOutputRegex("##");
        $this->systemControler->fluxDetailAction();
    }

    public function testIndex()
    {
        $this->getObjectInstancier()->setInstance(
            RedisWrapper::class,
            $this->createMock(RedisWrapper::class)
        );

        $this->expectOutputRegex("#Test du système#");
        $this->systemControler->indexAction();
    }

    /**
     * @throws NotFoundException
     */
    public function testListManquant()
    {
        $this->expectOutputRegex('#SEDA Standard#');
        $this->systemControler->missingConnecteurAction();
    }

    /**
     * @throws Exception
     */
    public function testExportAllMissingConnecteurAction()
    {
        $this->expectOutputRegex("#Content-type: application/zip#");
        $this->systemControler->exportAllMissingConnecteurAction();
    }

    public function testEmptyCacheAction()
    {
        $redisWrapper = $this->createMock(RedisWrapper::class);
        $this->getObjectInstancier()->setInstance(RedisWrapper::class, $redisWrapper);
        $this->expectException(LastMessageException::class);
        $this->expectExceptionMessage("Le cache Redis a été vidé");
        $this->systemControler->emptyCacheAction();
    }

    /**
     * @throws LastErrorException
     */
    public function testSendMailTest(): void
    {
        $mailerTransportTesting = new MailerTransportTesting();
        $mailer = new \Symfony\Component\Mailer\Mailer($mailerTransportTesting);
        $pastellMailer = $this->getObjectInstancier()->getInstance(Mailer::class);
        $pastellMailer->setMailer($mailer);

        $this->setPostInfo(['email' => 'test@libriciel.invalid']);
        try {
            $this->systemControler->mailTestAction();
            self::fail();
        } catch (LastMessageException $e) {
            self::assertStringContainsString(
                " Un email a été envoyé à l'adresse : test@libriciel.invalid",
                $e->getMessage()
            );
        }
        self::assertStringContainsString(
            'Subject: [Pastell] Mail de test',
            $mailerTransportTesting->getSentMessage()->getMessage()->toString()
        );
    }
}
