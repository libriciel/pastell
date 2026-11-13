<?php

declare(strict_types=1);

namespace Pastell\Tests\Service\Utilisateur;

use Pastell\Service\Utilisateur\UserCertificateService;
use Pastell\Utilities\Certificate;
use PastellTestCase;
use UtilisateurSQL;

final class UserCertificateServiceTest extends PastellTestCase
{
    private function getUserCertificateService(): UserCertificateService
    {
        return $this->getObjectInstancier()->getInstance(UserCertificateService::class);
    }

    private function getUtilisateurSQL(): UtilisateurSQL
    {
        return $this->getObjectInstancier()->getInstance(UtilisateurSQL::class);
    }

    public function testSetCertificate(): void
    {
        $certificate = new Certificate(
            file_get_contents(__DIR__ . '/../../Utilities/fixtures/autorite-cert.pem') ?: ''
        );

        self::assertTrue($this->getUserCertificateService()->setCertificate(1, $certificate));
        self::assertSame($certificate->getMD5(), $this->getUtilisateurSQL()->getInfo(1)['certificat_verif_number']);
    }

    public function testSetInvalidCertificate(): void
    {
        self::assertFalse($this->getUserCertificateService()->setCertificate(1, new Certificate('invalid')));
        self::assertSame('', (string)$this->getUtilisateurSQL()->getInfo(1)['certificat_verif_number']);
    }
}
