<?php

declare(strict_types=1);

use Pastell\Mailer\Mailer;
use Pastell\Service\MagicLink\MagicLinkService;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class MagicLinkServiceTest extends PastellTestCase
{
    use MailerTransportTestingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setMailerTransportForTesting();
    }

    private function getMagicLinkService(): MagicLinkService
    {
        return $this->getObjectInstancier()->getInstance(MagicLinkService::class);
    }

    private function getUtilisateurSQL(): UtilisateurSQL
    {
        return $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
    }

    private function extractTokenFromLastEmail(): string
    {
        $email = $this->getMailerTransport()->getSentMessage()->getOriginalMessage();
        static::assertInstanceOf(Email::class, $email);
        static::assertSame(
            1,
            preg_match('#/Connexion/magicLink\?token=([^"\s]+)#', (string)$email->getHtmlBody(), $matches),
        );
        return $matches[1];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testCreate(): void
    {
        $this->getMagicLinkService()->create(
            'Intervention test',
            24,
            1,
            'Dupont',
            'Jean',
            'jean.dupont@example.org',
        );

        $links = $this->getMagicLinkService()->getActiveLinks();
        static::assertCount(1, $links);
        $link = $links[0];
        static::assertSame('Jean', $link['titulaire_prenom']);
        static::assertSame('Dupont', $link['titulaire_nom']);
        static::assertSame('jean.dupont@example.org', $link['titulaire_email']);
        static::assertSame('Intervention test', $link['motif']);

        $userInfo = $this->getUtilisateurSQL()->getInfo((int)$link['id_u']);
        static::assertSame('jean.dupont@example.org', $userInfo['email']);
        static::assertSame('0', (string)$userInfo['is_enabled']);

        $roleUtilisateur = $this->getObjectInstancier()->getInstance(RoleUtilisateur::class);
        static::assertSame(1, (int)$roleUtilisateur->hasRole((int)$link['id_u'], 'admin', 0));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testCreateSendsEmail(): void
    {
        $this->getMagicLinkService()->create(
            'Intervention test',
            24,
            1,
            'Dupont',
            'Jean',
            'jean.dupont@example.org',
        );

        $message = $this->getMailerTransport()->getSentMessage()->getMessage()->toString();
        static::assertStringContainsString('jean.dupont@example.org', $message);
        static::assertStringContainsString('/Connexion/magicLink?token=', $message);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testValidToken(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $token = $this->extractTokenFromLastEmail();

        $link = $service->getValidLinkFromToken($token);

        static::assertNotNull($link);
        static::assertSame('Intervention', $link['motif']);
        static::assertSame((int)$service->getActiveLinks()[0]['id_u'], (int)$link['id_u']);
    }

    /**
     * @throws Exception
     */
    public function testUnknownToken(): void
    {
        static::assertNull($this->getMagicLinkService()->getValidLinkFromToken('un-token-inexistant'));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testExpiredToken(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Expirée', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $token = $this->extractTokenFromLastEmail();
        $link = $service->getActiveLinks()[0];

        static::getSQLQuery()->query(
            'UPDATE magic_link SET expires_at = ? WHERE id = ?',
            '2000-01-01 00:00:00',
            (int)$link['id'],
        );

        static::assertNull($service->getValidLinkFromToken($token));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testRevokedToken(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('À révoquer', 24, 1, 'Martin', 'Paul', 'paul.martin@example.org');
        $token = $this->extractTokenFromLastEmail();
        $link = $service->getActiveLinks()[0];

        $service->revoke((int)$link['id']);

        static::assertNull($service->getValidLinkFromToken($token));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testMultipleLinks(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Première', 24, 1, 'Un', 'Agent', 'agent1@example.org');
        $service->create('Seconde', 24, 1, 'Deux', 'Agent', 'agent2@example.org');

        static::assertCount(2, $service->getActiveLinks());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testRevoke(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('À révoquer', 24, 1, 'Martin', 'Paul', 'paul.martin@example.org');
        $link = $service->getActiveLinks()[0];
        $id_u = (int)$link['id_u'];

        $service->revoke((int)$link['id']);

        static::assertFalse($this->getUtilisateurSQL()->getInfo($id_u));
        static::assertSame([], $service->getActiveLinks());
        static::assertNull($service->getActiveLink((int)$link['id']));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testHistory(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Accès actif', 24, 1, 'Active', 'Toujours', 'actif@example.org');
        $service->create('Accès clôturé', 24, 1, 'Bernard', 'Alice', 'alice.bernard@example.org');
        $toRevoke = $service->getActiveLinks();
        $revokedLink = array_values(array_filter(
            $toRevoke,
            static fn(array $link): bool => $link['motif'] === 'Accès clôturé',
        ))[0];

        $service->revoke((int)$revokedLink['id']);

        $history = $service->getHistory();
        static::assertCount(1, $history);
        static::assertSame('Accès clôturé', $history[0]['motif']);
        static::assertSame('Bernard', $history[0]['titulaire_nom']);
        static::assertSame('Alice', $history[0]['titulaire_prenom']);
        static::assertSame('alice.bernard@example.org', $history[0]['titulaire_email']);
        static::assertNotNull($history[0]['revoked_at']);
    }

    /**
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testEmailFailureRollback(): void
    {
        $failingTransport = new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('SMTP indisponible');
            }

            public function __toString(): string
            {
                return 'failing';
            }
        };
        $this->getObjectInstancier()->getInstance(Mailer::class)
            ->setMailer(new \Symfony\Component\Mailer\Mailer($failingTransport));

        try {
            $this->getMagicLinkService()->create('Motif', 24, 1, 'Nom', 'Prenom', 'echec@example.org');
            static::fail('Une TransportExceptionInterface était attendue');
        } catch (TransportExceptionInterface) {
        }

        static::assertSame([], $this->getMagicLinkService()->getActiveLinks());
        static::assertSame([], $this->getMagicLinkService()->getHistory());
        static::assertCount(
            0,
            static::getSQLQuery()->query("SELECT id_u FROM utilisateur WHERE login LIKE 'support-%'"),
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testHistorySearch(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Maintenance', 24, 1, 'Bernard', 'Alice', 'alice.bernard@example.org');
        $service->create('Maintenance', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        foreach ($service->getActiveLinks() as $link) {
            $service->revoke((int)$link['id']);
        }

        $result = $service->getHistory('Bernard');

        static::assertCount(1, $result);
        static::assertSame('Bernard', $result[0]['titulaire_nom']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testPruneExpired(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Expirée', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];
        $id_u = (int)$link['id_u'];

        static::getSQLQuery()->query(
            'UPDATE magic_link SET expires_at = ? WHERE id = ?',
            '2000-01-01 00:00:00',
            (int)$link['id'],
        );

        static::assertSame(1, $service->pruneExpired());
        static::assertFalse($this->getUtilisateurSQL()->getInfo($id_u));
        static::assertSame(0, $service->pruneExpired());
    }
}
