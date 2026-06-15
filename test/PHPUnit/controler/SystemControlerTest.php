<?php

use Pastell\Mailer\Mailer;
use Pastell\Service\MagicLink\MagicLinkService;
use Pastell\Tests\MailerTransportTesting;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SystemControlerTest extends ControlerTestCase
{
    use MailerTransportTestingTrait;

    /** @var  SystemControler */
    private $systemControler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemControler = $this->getControlerInstance("SystemControler");
        $this->setMailerTransportForTesting();
    }

    private function getMagicLinkService(): MagicLinkService
    {
        return $this->getObjectInstancier()->getInstance(MagicLinkService::class);
    }

    /**
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    private function createMagicLink(string $motif = 'Intervention', string $nom = 'Dupont'): array
    {
        $this->getMagicLinkService()->create($motif, 24, 1, $nom, 'Jean', 'jean.dupont@example.org');
        return $this->getMagicLinkService()->getActiveLinks()[0];
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

    /**
     * @throws NotFoundException
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkActionListsActiveLinks(): void
    {
        $this->createMagicLink('Maintenance serveur', 'Durand');

        $this->expectOutputRegex('#Créer un accès temporaire.*Durand#s');
        $this->systemControler->magicLinkAction();
    }

    /**
     * @throws NotFoundException
     * @throws LastErrorException
     * @throws LastMessageException
     */
    public function testMagicLinkEditionActionDisplaysForm(): void
    {
        $this->expectOutputRegex("#Prénom de l'intervenant#");
        $this->systemControler->magicLinkEditionAction();
    }

    /**
     * @throws LastErrorException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testDoMagicLinkEditionActionCreatesLink(): void
    {
        $this->setPostInfo([
            'motif' => 'Intervention support',
            'duration' => 24,
            'nom' => 'Martin',
            'prenom' => 'Paul',
            'mail' => 'paul.martin@example.org',
        ]);

        try {
            $this->systemControler->doMagicLinkEditionAction();
            static::fail('Une LastMessageException était attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('paul.martin@example.org', $e->getMessage());
        }

        $links = $this->getMagicLinkService()->getActiveLinks();
        static::assertCount(1, $links);
        static::assertSame('Intervention support', $links[0]['motif']);
        static::assertStringContainsString(
            '/Connexion/magicLink?token=',
            $this->getMailerTransport()->getSentMessage()->getMessage()->toString(),
        );
    }

    /**
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testDoMagicLinkEditionActionRejectsIncompleteInput(): void
    {
        $this->setPostInfo([
            'motif' => '',
            'duration' => 0,
            'nom' => '',
            'prenom' => '',
            'mail' => '',
        ]);

        try {
            $this->systemControler->doMagicLinkEditionAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString('obligatoires', $e->getMessage());
        }

        static::assertSame([], $this->getMagicLinkService()->getActiveLinks());
    }

    /**
     * @throws NotFoundException
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkRevokeActionDisplaysConfirmation(): void
    {
        $link = $this->createMagicLink('Accès à révoquer');

        $this->setGetInfo(['id' => (int)$link['id']]);
        $this->expectOutputRegex('#sur le point de révoquer#');
        $this->systemControler->magicLinkRevokeAction();
    }

    /**
     * @throws NotFoundException
     * @throws LastMessageException
     */
    public function testMagicLinkRevokeUnknownLink(): void
    {
        $this->setGetInfo(['id' => 999999]);

        try {
            $this->systemControler->magicLinkRevokeAction();
            static::fail('Une LastErrorException était attendue');
        } catch (LastErrorException $e) {
            static::assertStringContainsString("n'existe pas ou n'est plus actif", $e->getMessage());
        }
    }

    /**
     * @throws LastErrorException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkRevoke(): void
    {
        $link = $this->createMagicLink('Accès à révoquer');
        $id_u = (int)$link['id_u'];

        $this->setPostInfo(['id' => (int)$link['id']]);

        try {
            $this->systemControler->doMagicLinkRevokeAction();
            static::fail('Une LastMessageException était attendue');
        } catch (LastMessageException $e) {
            static::assertStringContainsString('révoqué', $e->getMessage());
        }

        static::assertNull($this->getMagicLinkService()->getActiveLink((int)$link['id']));
        static::assertFalse(
            $this->getObjectInstancier()->getInstance(UtilisateurSQL::class)->getInfo($id_u)
        );
    }

    /**
     * @throws NotFoundException
     * @throws LastErrorException
     * @throws LastMessageException
     * @throws Exception
     * @throws TransportExceptionInterface
     */
    public function testMagicLinkHistory(): void
    {
        $link = $this->createMagicLink('Accès clôturé', 'Bernard');
        $this->getMagicLinkService()->revoke((int)$link['id']);

        $this->expectOutputRegex('#Historique des accès créés.*Bernard#s');
        $this->systemControler->magicLinkHistoryAction();
    }
}
