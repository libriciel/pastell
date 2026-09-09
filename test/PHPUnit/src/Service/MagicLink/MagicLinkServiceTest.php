<?php

declare(strict_types=1);

use Pastell\Mailer\Mailer;
use Pastell\Service\MagicLink\MagicLinkCodeStatus;
use Pastell\Service\MagicLink\MagicLinkService;
use Pastell\Service\Utilisateur\UtilisateurDeletionService;
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

    private function getUtilisateurDeletionService(): UtilisateurDeletionService
    {
        return $this->getObjectInstancier()->getInstance(UtilisateurDeletionService::class);
    }

    private function extractTokenFromLastEmail(): string
    {
        $sentMessages = $this->getMailerTransport()->getAllSentMessages();
        $email = end($sentMessages)->getOriginalMessage();
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
        static::assertSame('1', (string)$userInfo['is_enabled']);

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
    public function testResend(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $firstToken = $this->extractTokenFromLastEmail();
        $link = $service->getActiveLinks()[0];

        $service->resend($link['id']);
        $secondToken = $this->extractTokenFromLastEmail();

        static::assertNotSame($firstToken, $secondToken);
        static::assertNull($service->getValidLinkFromToken($firstToken));
        static::assertNotNull($service->getValidLinkFromToken($secondToken));
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testResendUnknownLink(): void
    {
        $this->expectException(UnrecoverableException::class);
        $this->getMagicLinkService()->resend('99999999-9999-4999-8999-999999999999');
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
            $link['id'],
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

        $service->revoke($link['id']);

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

        $service->revoke($link['id']);

        static::assertFalse($this->getUtilisateurSQL()->getInfo($id_u));
        static::assertSame([], $service->getActiveLinks());
        static::assertNull($service->getActiveLink($link['id']));
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testDeleteUserRevokesLink(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Compte supprimé à la main', 24, 1, 'Durand', 'Léa', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];
        $id_u = (int)$link['id_u'];

        $this->getUtilisateurDeletionService()->delete($id_u);

        static::assertSame([], $service->getActiveLinks());
        static::assertNull($service->getActiveLink($link['id']));

        $history = $service->getHistory();
        static::assertCount(1, $history);
        static::assertNotNull($history[0]['revoked_at']);
        static::assertSame('Du****', $history[0]['titulaire_nom']);

        $service->revoke($link['id']);
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

        $service->revoke($revokedLink['id']);

        $history = $service->getHistory();
        static::assertCount(1, $history);
        static::assertSame('Accès clôturé', $history[0]['motif']);
        static::assertSame('Be*****', $history[0]['titulaire_nom']);
        static::assertSame('Al***', $history[0]['titulaire_prenom']);
        static::assertSame('al***********@example.org', $history[0]['titulaire_email']);
        static::assertNotNull($history[0]['revoked_at']);
        static::assertSame((int)$revokedLink['id_u'], (int)$history[0]['id_u']);
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

        $userCountBefore = static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM utilisateur');

        try {
            $this->getMagicLinkService()->create('Motif', 24, 1, 'Nom', 'Prenom', 'echec@example.org');
            static::fail('Une TransportExceptionInterface était attendue');
        } catch (TransportExceptionInterface) {
        }

        static::assertSame([], $this->getMagicLinkService()->getActiveLinks());
        static::assertSame([], $this->getMagicLinkService()->getHistory());
        static::assertSame(
            $userCountBefore,
            static::getSQLQuery()->queryOne('SELECT COUNT(*) FROM utilisateur'),
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
            $service->revoke($link['id']);
        }

        $result = $service->getHistory('Be');

        static::assertCount(1, $result);
        static::assertSame('Be*****', $result[0]['titulaire_nom']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testRevokeMasksTitulaire(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('À révoquer', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $link = $service->getActiveLinks()[0];

        $service->revoke($link['id']);

        $history = $service->getHistory();
        static::assertCount(1, $history);
        static::assertSame('Du****', $history[0]['titulaire_nom']);
        static::assertSame('Je**', $history[0]['titulaire_prenom']);
        static::assertSame('je*********@example.org', $history[0]['titulaire_email']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testPruneExpiredMasksTitulaire(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Expirée', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];

        static::getSQLQuery()->query(
            'UPDATE magic_link SET expires_at = ? WHERE id = ?',
            '2000-01-01 00:00:00',
            $link['id'],
        );

        static::assertSame(1, $service->pruneExpired());

        $history = $service->getHistory();
        static::assertCount(1, $history);
        static::assertSame('Du****', $history[0]['titulaire_nom']);
        static::assertSame('Le*', $history[0]['titulaire_prenom']);
        static::assertSame('le********@example.org', $history[0]['titulaire_email']);
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
            $link['id'],
        );

        static::assertSame(1, $service->pruneExpired());
        static::assertFalse($this->getUtilisateurSQL()->getInfo($id_u));
        static::assertSame(0, $service->pruneExpired());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testPruneHistoryDeletesRevokedBeyondRetention(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Ancienne', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];
        $service->revoke($link['id']);

        static::getSQLQuery()->query(
            'UPDATE magic_link SET revoked_at = ? WHERE id = ?',
            date(Date::DATE_ISO, strtotime('-2 year')),
            $link['id'],
        );

        static::assertCount(1, $service->getHistory());
        static::assertSame(1, $service->pruneHistory());
        static::assertCount(0, $service->getHistory());
        static::assertSame(0, $service->pruneHistory());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testPruneHistoryDeletesExpiredBeyondRetention(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Expirée', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];

        static::getSQLQuery()->query(
            'UPDATE magic_link SET expires_at = ? WHERE id = ?',
            date(Date::DATE_ISO, strtotime('-2 year')),
            $link['id'],
        );
        $service->pruneExpired();

        static::getSQLQuery()->query(
            'UPDATE magic_link SET revoked_at = ? WHERE id = ?',
            date(Date::DATE_ISO, strtotime('-2 year')),
            $link['id'],
        );

        static::assertCount(1, $service->getHistory());
        static::assertSame(1, $service->pruneHistory());
        static::assertCount(0, $service->getHistory());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testPruneHistoryKeepsUncleanedLink(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Jamais nettoyée', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];

        static::getSQLQuery()->query(
            'UPDATE magic_link SET expires_at = ? WHERE id = ?',
            date(Date::DATE_ISO, strtotime('-2 year')),
            $link['id'],
        );

        static::assertSame(0, $service->pruneHistory());
        static::assertCount(1, $service->getHistory());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testPruneHistoryKeepsRecentlyClosed(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Récente', 24, 1, 'Durand', 'Lea', 'lea.durand@example.org');
        $link = $service->getActiveLinks()[0];
        $service->revoke($link['id']);

        static::assertSame(0, $service->pruneHistory());
        static::assertCount(1, $service->getHistory());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testCreateGeneratesSixDigitCode(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');

        $code = $service->getActiveLinks()[0]['code'];
        static::assertMatchesRegularExpression('/^\d{6}$/', (string)$code);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testCheckValidCode(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $token = $this->extractTokenFromLastEmail();
        $code = (string)$service->getActiveLinks()[0]['code'];

        $result = $service->checkCode($token, $code);

        static::assertSame(MagicLinkCodeStatus::Valid, $result->status);
        static::assertNotNull($result->link);
        static::assertSame((int)$service->getActiveLinks()[0]['id_u'], (int)$result->link['id_u']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testCheckWrongCodeDecrementsRemainingAttempts(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $token = $this->extractTokenFromLastEmail();

        $result = $service->checkCode($token, '000000');

        static::assertSame(MagicLinkCodeStatus::WrongCode, $result->status);
        static::assertSame(2, $result->remainingAttempts);
        static::assertCount(1, $service->getActiveLinks());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws UnrecoverableException
     * @throws ConflictException
     * @throws Exception
     */
    public function testCheckCodeRevokesAfterMaxAttempts(): void
    {
        $service = $this->getMagicLinkService();
        $service->create('Intervention', 24, 1, 'Dupont', 'Jean', 'jean.dupont@example.org');
        $token = $this->extractTokenFromLastEmail();
        $id_u = (int)$service->getActiveLinks()[0]['id_u'];

        $wrongCode = ((string)$service->getActiveLinks()[0]['code'] === '000000') ? '111111' : '000000';

        static::assertSame(MagicLinkCodeStatus::WrongCode, $service->checkCode($token, $wrongCode)->status);
        static::assertSame(MagicLinkCodeStatus::WrongCode, $service->checkCode($token, $wrongCode)->status);
        static::assertSame(MagicLinkCodeStatus::Revoked, $service->checkCode($token, $wrongCode)->status);

        static::assertSame([], $service->getActiveLinks());
        static::assertCount(1, $service->getHistory());
        static::assertFalse($this->getUtilisateurSQL()->getInfo($id_u));
        static::assertSame(MagicLinkCodeStatus::InvalidLink, $service->checkCode($token, $wrongCode)->status);
    }

    /**
     * @throws Exception
     */
    public function testCheckCodeInvalidLink(): void
    {
        $result = $this->getMagicLinkService()->checkCode('token-inexistant', '123456');
        static::assertSame(MagicLinkCodeStatus::InvalidLink, $result->status);
    }
}
