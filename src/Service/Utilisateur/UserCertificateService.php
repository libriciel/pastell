<?php

declare(strict_types=1);

namespace Pastell\Service\Utilisateur;

use Pastell\Utilities\Certificate;
use UtilisateurSQL;

final class UserCertificateService
{
    public function __construct(
        private readonly UtilisateurSQL $utilisateurSQL,
    ) {
    }

    public function setCertificate(int $id_u, Certificate $certificate): bool
    {
        if (!$certificate->isValid()) {
            return false;
        }
        $this->utilisateurSQL->updateCertificat($id_u, $certificate->getContent(), $certificate->getMD5());
        return true;
    }
}
